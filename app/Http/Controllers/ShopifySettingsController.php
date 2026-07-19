<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShopifySettingsController extends Controller
{
    function index(){
        return view('shopify.settings');
    }
}
