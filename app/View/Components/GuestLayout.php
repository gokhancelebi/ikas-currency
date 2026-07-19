<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {}

    /**
     * Auth / guest pages — same shell as error pages, with card wrapper.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
