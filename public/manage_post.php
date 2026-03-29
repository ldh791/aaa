<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\Http\ManagePostAction;

(new ManagePostAction())->handle();
