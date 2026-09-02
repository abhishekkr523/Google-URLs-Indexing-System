<?php

test('guests are redirected to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertRedirect('/login');
});
