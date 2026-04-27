<?php
require __DIR__ . '/../app/bootstrap.php';

set_flash('error', 'Password recovery is not available right now.');
redirect('login.php');
