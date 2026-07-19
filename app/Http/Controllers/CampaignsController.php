<?php

namespace App\Http\Controllers;
use App\Models\Campaign;
use App\Models\Collection;
use Illuminate\Http\Request;

class CampaignsController extends Controller
{

    public function index()
    {

        $collections = Collection::all();

        return view('campaigns.index', compact('collections'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }


    public function update(Request $request, $id)
    {
        $collection_id = $request->input('ikas_category_id');
        $campaign = Collection::where('ikas_category_id', $collection_id)->first();
        $campaign->update($request->except(['_token', '_method']));
        return response()->json(['msg' => __('campaigns.updated', ['name' => $campaign->name]), 'status' => 'success']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
