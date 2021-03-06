<?php

namespace App\Http\Requests\Backend;

use App\Http\Requests\Request;

class TournamentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
            'link' => 'url',
            'status' => 'required|integer',
            'team_id' => 'required|integer|exists:teams,id',
            'league_id' => 'required|integer|exists:leagues,id',
        ];
    }
}
