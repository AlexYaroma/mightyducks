<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Yangqi\Htmldom\Htmldom;

class ParsePlayerPhotos extends CommandParent
{
    const TEAM_ID = 1;
    const DOMAIN = 'http://mls.od.ua';
    const DEFAULT_IMG = '/media/bearleague/player_st.png.pagespeed.ce.8XofNKXlcR.png';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatephotos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parsing new player photos';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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


        $html = new Htmldom($team->link);
        $team_table = $html->find('table.team-list', 0);

        if ($team_table == null) {
            $this->error('Team table not found on this page: ' . $team->link);
            exit;
        }

        $player_links = [];
        foreach ($team_table->find('p.player-name a') as $link) {
            if (isset($link->href)) {
                array_push($player_links, $link->href);
            }
        }

        if (count($player_links) < 1) {
            $this->error('No players found');
            exit;
        }

        $this->comment('Found players: ' . count($player_links));
        $bar = $this->output->createProgressBar(count($player_links));
        foreach ($player_links as $link) {
            if (strpos($link, self::DOMAIN) === false) {
                $link = self::DOMAIN . $link;
            }

            $player = new Player();
            $player->team_id = self::TEAM_ID;
            $player->mls_id = $this->parseIdFromUrl($link);

            $player_html = new Htmldom($link);

            if ($player_html == null) {
                continue;
            }

            $player_table = $player_html->find('table.adf-fields-table', 0);

            if ($player_table == null) {
                continue;
            }


            foreach ($player_table->find('tr') as $row) {
                $td1 = $row->find('td', 0);
                $td2 = $row->find('td', 1);

                if ($td1 != null && strpos($td1->innertext, 'Полное имя') !== false) {
                    $player->name = $td2->plaintext;
                }

                if ($td1 != null && strpos($td1->innertext, 'Дата рождения') !== false) {
                    $player->date_of_birth = Carbon::parse($td2->plaintext);
                }
            }

            $check_player = Player::where('mls_id', $player->mls_id)
                ->where('name', $player->name)
                ->where('team_id', $player->team_id)
                ->where('date_of_birth', $player->date_of_birth)
                ->first();

            if (!$check_player) {
                $this->timedInfo('Not found: ' . $player->name);
                continue;
            }

            $player->id = $check_player->id;
            $img = $player_html->find('div#etab_player_div div.gray-box a.gray-box-img', 0);

            if ($img != null && $img->href != null && strpos($img->href, self::DEFAULT_IMG) === false) {
                $src = $img->href;
                preg_match('/\.php\?src=([0-9a-z\/]+)\.([a-z]{3,4})/', $src, $matches);

                if (isset($matches[1])) {
                    $src = self::DOMAIN . '/' . $matches[1] . '.' . $matches[2];
                }

                copy($src, public_path() . '/img/avatars/players/' . $player->id . '.jpg');

                $this->timedInfo('Updated photo for: ' . $player->name);
            }

            $bar->advance();
        }

        $bar->finish();


        $this->endLog();
    }

    private function parseIdFromUrl($link)
    {
        $arr = explode("/", $link);
        $id = (int)end($arr);

        if (is_integer($id)) {
            return $id;
        }

        return null;
    }
}
