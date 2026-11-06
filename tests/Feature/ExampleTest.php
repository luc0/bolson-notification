<?php

it('redirects guests to the login', function () {
    $this->get('/')->assertRedirect('/login');
});
