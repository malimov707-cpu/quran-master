<?php

test('the application redirects root to dashboard', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});
