<?php

test('guests are sent to sign in before opening the website journal', function () {
    $this->get('/all/trading-journals')->assertRedirect(route('tradinghub.login'));
    $this->get('/add/trading-journal')->assertRedirect(route('tradinghub.login'));
});
