<?php

namespace App\Http\Controllers\Api;

use App\Models\Api\Team;
use App\Models\League;
use App\Models\Team as TeamModel;
use App\Models\Tournament;
use App\Repositories\GameRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LeaguesController extends Controller
{
    public function all()
    {
        return League::all();
    }

    public function tournaments(Request $request)
    {
        $leagueIds = $request->get('leagues');

        if (empty($leagueIds)) {
            return [];
        }

        return Tournament::whereIn('league_id', $leagueIds)->get();
    }
}