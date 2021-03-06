<?php

namespace App\Console\Commands;

use App\Models\Console\GameMlsEntity;
use App\Models\Team;
use App\Repositories\GameRepository;
use App\Repositories\TournamentRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Yangqi\Htmldom\Htmldom;

class ParseHistorySchedule extends CommandParent
{
    const TEAM_ID = 1;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsehistory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parsing history team schedule by tournaments table';

    protected $tournament;

    public function __construct()
    {
        parent::__construct();

        $this->tournament = new TournamentRepository();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->startLog();
        $team = Team::find(self::TEAM_ID);

        if ($team == null) {
            $this->error('Team with id: ' . self::TEAM_ID . ' not found');
            exit;
        }

        $tournamentList = $this->tournament->getPassive();

        if ($tournamentList->count() < 1) {
            $this->error('No tournaments');
            exit;
        }

        $this->info('Tournaments count: ' . $tournamentList->count());

        foreach ($tournamentList as $key => $tournament) {
            $this->info('Tournament ' . ++$key . ': ' . $tournament->name);

            $html = new Htmldom($tournament->link);
            $table = $html->find('table.match-day', 0);

            if ($table == null) {
                $this->error('No matchday info');
                continue;
            }

            $gameList = $this->parseGameLinks($table, $team);

            $gameList = collect($gameList);

            $this->info('Games found: ' . $gameList->count());

            foreach($gameList as $game) {
                $game->setTournamentId($tournament->id);
                $game->setTeamId($team->id);
                $game->setSearchTeamName($team->name);
                (new GameRepository())->addParsedGame($game);
            }
        }

        $this->endLog();
    }

    private function parseGameLinks($table, $team)
    {
        $gameList = [];

        $roundValue = null;
        foreach ($table->find('tr') as $row) {
            if (!$this->checkIfNeededTeamRow($row, $team)) {
                continue;
            }

            $gameEntity = $this->fillGameMlsEntityForTeam($row, $roundValue);

            if ($gameEntity->isValid()) {
                array_push($gameList, $gameEntity);
            }
        }

        return $gameList;
    }

    private function fillGameMlsEntityForTeam($row, $round)
    {
        $gameEntity = new GameMlsEntity();

        $roundRow = $row->find('td.m_name', 0);
        if ($roundRow) {
            $round = $roundRow->innertext;
            $round = substr($round, 0, strpos($round, '(Группа'));
            $round = trim($round);
            $gameEntity->setRound($round);
            preg_match('/>([^<]+)</', $roundRow->innertext, $matches);
            if (isset($matches[1]) && $matches[1]) {
                $gameEntity->setMatchDate(Carbon::parse($matches[1]));
            }
        }
        $teamHome = $row->find('td.team-h span', 0);
        if ($teamHome) {
            $gameEntity->setTeamHome($teamHome->innertext);
        }

        $teamHomeIcon = $row->find('td.team-ico-h-l div.team-embl img', 0);
        if ($teamHomeIcon) {
            $gameEntity->setTeamHomeIcon($teamHomeIcon->src);
        }

        $teamVisit = $row->find('td.team-a', 0);
        if ($teamVisit) {
            $gameEntity->setTeamVisit($teamVisit->innertext);
        }

        $teamVisitIcon = $row->find('td.team-ico-a div.team-embl img', 0);
        if ($teamVisitIcon) {
            $gameEntity->setTeamVisitIcon($teamVisitIcon->src);
        }

        $gameLinkPlayed = $row->find('a.button-details', 0);
        if ($gameLinkPlayed) {
            $gameEntity->setLink($gameLinkPlayed->href);
        }

        $placeTd = $row->find('td', -3);

        if ($placeTd) {
            $place = $placeTd->find('a', 0);
            if ($place) {
                $gameEntity->setPlace($place->innertext);
            }
        }

        return $gameEntity;
    }

    private function checkIfNeededTeamRow($row, $team)
    {
        $home = $row->find('td.team-h span', 0);
        $visitor = $row->find('td.team-a', 0);

        if (
            $home != null && $visitor != null &&
            ($home->innertext == $team->name || $visitor->innertext == $team->name)
        ) {
            return true;
        }

        return false;
    }
}
