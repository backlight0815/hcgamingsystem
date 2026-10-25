<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => 'test.user@gmail.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'user_role' => 700,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('final_exam.intro'));
});
