<?php

declare(strict_types=1);

define('JWT_SECRET', 'ISSUomyb2XrAVezxBOaEVi6zueL_yxdhdXEX2ivOpAg'); // HS256: openssl rand -base64 32 | tr '+/' '-_' | tr -d '='

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'evcal');
define('DB_USER', 'evcal');
define('DB_PASS', 'evcal');
