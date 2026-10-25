<?php

it('returns a successful response', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('home_slides')) {
        $this->markTestSkipped('Legacy homepage CMS tables are supplied by the production database import.');
    }

    $response = $this->get('/');

    $response->assertStatus(200);
});
