<?php

namespace App\Repositories;

use App\Models\Game;
use App\Models\Stat;
use Illuminate\Support\Facades\DB;

class StatRepository
{
    public static function createOrUpdateGameVisit(array $data, $visit)
    {
        $stat = Stat::where('game_id', $data['game_id'])
            ->where('player_id', $data['player_id'])
            ->whereNotNull(Stat::VISIT)->first();

        if (!$stat) {
            if ($visit) {
                Stat::create([
                    'game_id' => $data['game_id'],
                    'player_id' => $data['player_id'],
                    Stat::VISIT => $visit
                ]);
            }
        } else {
            if ($visit) {
                $stat->visit = $visit;

                $stat->save();
            } else {
                $stat->delete();
            }
        }
    }

    public static function getVisitsForGame($gameId)
    {
        return Stat::where('game_id', $gameId)->whereNotNull(Stat::VISIT)->get();
    }

    public static function getActiveVisitsForGame($gameId)
    {
        return Stat::where('game_id', $gameId)->whereIn(Stat::VISIT, array_keys(Stat::$visitList))->get();
    }

    public static function getApprovedVisitsForGame($gameId)
    {
        return Stat::where('game_id', $gameId)->where(Stat::VISIT, Stat::GAME_VISITED)->get();
    }

    public static function getSkipVisitsForGame($gameId)
    {
        return Stat::where('game_id', $gameId)->where(Stat::VISIT, Stat::GAME_NOT_VISITED)->get();
    }

    public static function addVisit($game_id, $player_id, $visit = Stat::VISIT)
    {
        $stat = Stat::where('game_id', $game_id)
            ->where('player_id', $player_id)
            ->whereNotNull(Stat::VISIT)
            ->first();

        if ($stat) {
            $stat->{Stat::VISIT} = $visit;

            $stat->save();
        } else {
            Stat::create([
                'game_id' => $game_id,
                'player_id' => $player_id,
                Stat::VISIT => $visit
            ]);
        }
    }


    public static function getPlayersStatistics($playerList)
    {
        $data = [];

        $stats = DB::table('stats')
            ->select(DB::raw('stats.player_id, COALESCE(sum(stats.visit),0) as visits, COALESCE(sum(stats.goal),0) as goals,
            COALESCE(sum(stats.assist),0) as assists, COALESCE(sum(stats.yc),0) as ycs, COALESCE(sum(stats.rc),0) as rcs'))
            ->whereIn('stats.player_id', $playerList->lists('id')->all())
            ->where(function ($query) {
                $query->whereNull('stats.visit')
                    ->orWhere('stats.visit', '=', Stat::GAME_VISITED);
            })
            ->join('games', 'games.id', '=', 'stats.game_id')
            ->where('games.status', Game::getPlayedStatus())
            ->groupBy('stats.player_id')
            ->get();

        foreach ($stats as $stat) {
            $data[$stat->player_id] = $stat;
        }

        $result = [];

        foreach ($playerList as $player) {
            $result[$player->id] = isset($data[$player->id]) ? $data[$player->id] : self::getEmptyStat();
        }

        return $result;
    }


    public static function getFilteredPlayersStatistics($playerList, $selectedTournamentList)
    {
        $data = [];

        $query = DB::table('stats')
            ->select(DB::raw('stats.player_id, COALESCE(sum(stats.visit),0) as visits,
            COALESCE(sum(stats.goal),0) as goals, COALESCE(sum(stats.assist),0) as assists,
            COALESCE(sum(stats.yc),0) as ycs, COALESCE(sum(stats.rc),0) as rcs'))
            ->whereIn('stats.player_id', $playerList)
            ->where(function ($query) {
                $query->whereNull('stats.visit')
                    ->orWhere('stats.visit', '=', Stat::GAME_VISITED);
            })
            ->join('games', 'games.id', '=', 'stats.game_id')
            ->where('games.status', Game::getPlayedStatus())
            ->groupBy('stats.player_id');

        if ($selectedTournamentList) {
            $query->whereIn('games.tournament_id', $selectedTournamentList);
        }

        $stats = $query->get();

        foreach ($stats as $stat) {
            $data[$stat->player_id] = $stat;
        }

        $result = [];

        foreach ($playerList as $player) {
            $result[$player] = isset($data[$player]) ? $data[$player] : self::getEmptyStat();
        }

        return $result;
    }

    private static function getEmptyStat()
    {
        $emptyStat = new \stdClass();
        $emptyStat->visits = 0;
        $emptyStat->goals = 0;
        $emptyStat->assists = 0;
        $emptyStat->ycs = 0;
        $emptyStat->rcs = 0;

        return $emptyStat;
    }

    public static function getByPlayerId($playerId, $leagueId = null)
    {
        $query = DB::table('stats')
            ->select(DB::raw('COALESCE(sum(stats.visit),0) as visits, COALESCE(sum(stats.goal),0) as goals,
            COALESCE(sum(stats.assist),0) as assists, COALESCE(sum(stats.yc),0) as ycs, COALESCE(sum(stats.rc),0) as rcs'))
            ->where('stats.player_id', $playerId)
            ->where(function ($query) {
                $query->whereNull('stats.visit')
                    ->orWhere('stats.visit', '=', Stat::GAME_VISITED);
            })
            ->join('games', 'games.id', '=', 'stats.game_id')
            ->where('games.status', Game::getPlayedStatus())
            ->groupBy('stats.player_id');

        if ($leagueId) {
            $query->join('tournaments', 'games.tournament_id', '=', 'tournaments.id')
                ->join('leagues', 'tournaments.league_id', '=', 'leagues.id')
                ->where('leagues.id', $leagueId);
        }

        $stats = $query->first();

        return $stats ? $stats : self::getEmptyStat();
    }

    public static function getFilteredByPlayerId($player_id, $leagueIds, $tournamentIds)
    {
        $query = DB::table('stats')
            ->select(DB::raw('COALESCE(sum(stats.visit),0) as visits, COALESCE(sum(stats.goal),0) as goals,
            COALESCE(sum(stats.assist),0) as assists, COALESCE(sum(stats.yc),0) as ycs, COALESCE(sum(stats.rc),0) as rcs'))
            ->where('stats.player_id', $player_id)
            ->where(function ($query) {
                $query->whereNull('stats.visit')
                    ->orWhere('stats.visit', '=', Stat::GAME_VISITED);
            })
            ->join('games', 'games.id', '=', 'stats.game_id')
            ->where('games.status', Game::getPlayedStatus())
            ->groupBy('stats.player_id');

        if (!empty($tournamentIds)) {
            $query->whereIn('games.tournament_id', $tournamentIds);
        }

        if (!empty($leagueIds)) {
            $query->join('tournaments', 'tournaments.id', '=', 'games.tournament_id');
            $query->whereIn('tournaments.league_id', $leagueIds);
        }

        $stats = $query->first();

        return $stats ? $stats : self::getEmptyStat();
    }

    public static function getStatsByGameId($game_id)
    {
        $stats[Stat::GOAL] = Stat::where('game_id', $game_id)->whereNotNull(Stat::GOAL)->get();
        $stats[Stat::ASSIST] = Stat::where('game_id', $game_id)->whereNotNull(Stat::ASSIST)->get();
        $stats[Stat::YELLOW_CARD] = Stat::where('game_id', $game_id)->whereNotNull(Stat::YELLOW_CARD)->get();
        $stats[Stat::RED_CARD] = Stat::where('game_id', $game_id)->whereNotNull(Stat::RED_CARD)->get();

        return $stats;
    }

    public function triggerUserVisit($gameId, $playerId)
    {
        $stat = Stat::where('game_id', $gameId)
            ->where('player_id', $playerId)
            ->whereNotNull(Stat::VISIT)->first();

        if ($stat == null) {
            Stat::create([
                'game_id' => $gameId,
                'player_id' => $playerId,
                Stat::VISIT => Stat::GAME_VISITED
            ]);
        } elseif ($stat->visit == Stat::GAME_VISITED) {
            $stat->update([Stat::VISIT => Stat::GAME_NOT_VISITED]);
        } else {
            $stat->update([Stat::VISIT => Stat::GAME_VISITED]);
        }
    }
}