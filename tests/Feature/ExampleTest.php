<?php

it('returns a successful response for the health check endpoint', function () {
    $response = $this->get('/up');

    $response->assertOk();
});
