<?php

namespace App\Repositories;

use App\Events\GameDateChange;
use App\Http\Requests\Backend\GameRequest;
use App\Http\Requests\Backend\StatRequest;
use App\Models\Console\GameMlsBO;
use App\Models\Console\GameMlsEntity;
use App\Models\Game;
use App\Models\Player;
use App\Models\Stat;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GameRepository
{
    public static function saveQuickVisit(Request $request)
    {
        if (!Game::find($request->get('game_id'))) {
            return;
        }

        self::removeVisits($request->get('game_id'));
        foreach ($request->all() as $key => $input) {
            if (strpos($key, 'player_') !== false && $input > 0) {
                $id = str_replace('player_', '', $key);
                if (Player::find($id)) {

                    StatRepository::addVisit($request->get('game_id'), $id, $input);
                }
            }
        }
    }

    public static function getListByTeamId($id, $limit = null)
    {
        $games = Game::where('team_id', $id)->orderBy('date', 'desc');
        if ($limit) {
            $games->limit($limit);
        }

        return $games->get();
    }

    public static function getListByTeamIdForLeague($teamId, $leagueId)
    {
        $tournamentIds = Tournament::where('league_id', $leagueId)->get()->pluck('id')->toArray();
        return Game::where('team_id', $teamId)->whereIn('tournament_id', $tournamentIds)->orderBy('date', 'desc')->get();
    }

    public static function getWithVideoSortedByDate($teamId)
    {
        return Game::where('team_id', $teamId)->whereNotNull('youtube')->where('youtube', '!=', '')->orderBy('date', 'desc')->get();
    }

    public function addParsedGame(GameMlsEntity $gameEntity)
    {
        $gameBO = new GameMlsBO($gameEntity);
        $data = $gameBO->toArray();

        $validator = Validator::make($data, GameRequest::getRules());
        if (!$validator->fails()) {
            $game = Game::where('mls_id', $data['mls_id'])->first();
            if (!$game) {
                $game = Game::create($data);
                if (strpos($data['icon'], 'players-ico.png') === false && $data['icon'] != config('mls.domain')) {
                    copy($data['icon'], public_path() . '/img/team_logos/' . str2url($game->team) . '.png');
                }

                return $game;
            } elseif (isset($data['date']) && $game->status == Game::getNonePlayedStatus() && $game->date->format('d-m-Y H:i') != Carbon::parse($data['date'])->format('d-m-Y H:i')) {
                $data['game_id'] = $game->id;
                event(new GameDateChange($data));
            }
        }

        return false;
    }

    public function getOpennedForLeague($leagueId)
    {
        $tournamentIds = Tournament::where('league_id', $leagueId)->get()->pluck('id')->toArray();
        return Game::where('status', Game::getNonePlayedStatus())->whereIn('tournament_id', $tournamentIds)->get();
    }

    public function saveScore(Game $game, array $data)
    {
        $game->score1 = $data['score1'];
        $game->score2 = $data['score2'];
        $game->status = Game::getOnlyResultStatus();

        $game->save();
    }

    public function saveGameStats(Game $game, array $stats)
    {
        $stats = $this->reformatStats($stats);

        foreach ($stats as $stat) {
            $stat['game_id'] = $game->id;
            $this->saveGameStat($stat);
        }

        $game->status = Game::getPlayedStatus();
        $game->save();
    }

    private function saveGameStat(array $stat)
    {
        $validator = Validator::make($stat, StatRequest::getRules());

        if (!$validator->fails()) {
            $statElem = Stat::where('game_id', $stat['game_id'])
                ->where('player_id', $stat['player_id'])
                ->whereNotNull($stat['parameter'])
                ->first();

            $data = [
                'game_id' => $stat['game_id'],
                'player_id' => $stat['player_id'],
                $stat['parameter'] => $stat['value']
            ];
            if ($statElem) {
                $statElem->update($data);
            } else {
                Stat::create($data);
            }
        }
    }

    private function reformatStats(array $stats)
    {
        $formated = [];

        foreach ($stats as $stat) {

            $player = Player::where('name', 'like', '%' . $stat['player'] . '%')->first();
            if (!$player || empty($stat['player'])) {
                continue;
            }

            if (isset($formated[$stat['parameter']][$player->id])) {
                $formated[$stat['parameter']][$player->id]['value']++;
            } else {
                $formated[$stat['parameter']][$player->id] = [
                    'value' => 1,
                    'player_id' => $player->id,
                    'parameter' => $stat['parameter']
                ];
            }
        }

        $result = [];

        foreach ($formated as $subarr) {
            foreach ($subarr as $arr) {
                array_push($result, $arr);
            }
        }

        return $result;
    }

    public static function getNextGameByTeamId($teamId)
    {
        return Game::where('team_id', $teamId)
            ->where('status', Game::getNonePlayedStatus())
            ->where('date', '>=', date('Y-m-d H:i:s', time()))
            ->orderBy('date', 'asc')->first();
    }

    public static function getNextInSchedule($teamId, $count)
    {
        return Game::where('team_id', $teamId)
            ->where('status', Game::getNonePlayedStatus())
            ->where('date', '>=', date('Y-m-d H:i:s', time()))
            ->orderBy('date', 'asc')->limit($count)->get();
    }

    public static function getLastFinished($teamId, $count)
    {
        return Game::where('team_id', $teamId)
            ->where('status', Game::getPlayedStatus())
//            ->where('date', '<=', date('Y-m-d H:i:s', time()))
            ->orderBy('date', 'desc')->limit($count)->get();
    }

    public static function getSiblings(Game $game)
    {
        $siblings = [];
        $siblings['prev'] = Game::where('team_id', $game->team_id)
            ->where('date', '<', $game->date)
            ->orderBy('date', 'desc')->first();

        $siblings['next'] = Game::where('team_id', $game->team_id)
            ->where('date', '>', $game->date)
            ->orderBy('date', 'asc')->first();;

        return $siblings;
    }

    private static function setNotVisited($id)
    {
        Stat::where('game_id', $id)
            ->whereNotNull(Stat::VISIT)
            ->update([Stat::VISIT => Stat::GAME_NOT_VISITED]);
    }

    private static function removeVisits($id)
    {
        Stat::where('game_id', $id)
            ->whereNotNull(Stat::VISIT)
            ->delete();
    }

    public function processParsedGame($data)
    {
        $validator = Validator::make($data, GameRequest::getRules());
        if (!$validator->fails()) {
            $game = Game::where('tournament_id', $data['tournament_id'])->where('team', $data['team'])->first();
            if (!$game) {
                Game::create($data);
            } elseif (isset($data['date']) && $game->status == Game::getNonePlayedStatus() && $game->date->format('d-m-Y H:i') != Carbon::parse($data['date'])->format('d-m-Y H:i')) {
                $data['game_id'] = $game->id;
                event(new GameDateChange($data));
            }
        }
    }

    public function getFirstGameToSchedule($period)
    {
        $date = Carbon::now();
        $game = Game::where('date', '>=', $date->format('Y-m-d H:m:s'))
            ->where('date', '<=', $date->addDays($period)->format('Y-m-d 23:59:59'))
            ->where('reminder', Game::MSG_NOT_SENT)->first();

        return $game;
    }

    public function markAsVoteMsgSent($game, $msgId)
    {
        $game->update(['telegram_msg_id' => $msgId, 'reminder' => Game::MSG_SENT]);
    }

    public function getGameVoteNeedsToBeClosed()
    {
        $date = Carbon::now();
        $game = Game::where('date', '<=', $date->format('Y-m-d H:m:s'))->where('reminder', Game::MSG_SENT)
            ->orderBy('date', 'asc')->first();

        return $game;
    }

    public function markAsVoteClosed($game)
    {
        $game->update(['reminder' => Game::MSG_CLOSED]);
    }

    public function getVisitsForVote($gameId)
    {
        $msg = '';
        $stats = StatRepository::getApprovedVisitsForGame($gameId);
        if ($stats->count() > 0) {
            $msg .= config('mls.chat_game_msg_visits_telegram');
            foreach ($stats as $index => $stat) {
                $player = Player::find($stat->player_id);
                $msg .= ++$index . ' ' . $player->getShortName() . "\n";
            }
        }

        return $msg;
    }
    public function getSkipsForVote($gameId)
    {
        $msg = '';
        $stats = StatRepository::getSkipVisitsForGame($gameId);
        if ($stats->count() > 0) {
            $msg .= config('mls.chat_game_msg_skip_telegram');
            foreach ($stats as $index => $stat) {
                $player = Player::find($stat->player_id);
                $msg .= ++$index . ' ' . $player->getShortName() . "\n";
            }
        }

        return $msg;
    }

    public function getNextGame()
    {
        return Game::where('status', Game::getNonePlayedStatus())
            ->where('date', '>=', date('Y-m-d H:i:s', time()))
            ->orderBy('date', 'asc')->first();
    }

    public function getGameWithNewVideo($fromDate)
    {
        return Game::whereNotNull('youtube')
            ->where('youtube', '!=', '')
            ->whereNull('telegram_youtube_msg_id')
            ->where('date', '>=', $fromDate)
            ->orderBy('date', 'asc')->first();
    }

    public function markAsVideoMsgSent($game, $msgId)
    {
        $game->update(['telegram_youtube_msg_id' => $msgId]);
    }
}