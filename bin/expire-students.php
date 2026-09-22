<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\User;

User::blockExpiredStudents();
echo "Сроки доступа курсантов проверены.\n";
