<?php
require_once __DIR__ . '/../admin/UsersModel.php';

class UserProtectionTest
{
    public static function run(): void
    {
        if (!method_exists('UsersModel', 'isProtectedUser')) {
            throw new RuntimeException('UsersModel::isProtectedUser() is missing');
        }

        $cases = [
            ['id' => 1, 'name' => 'Wycliffe Bunde', 'email' => 'user_1@secured.local', 'expected' => true],
            ['id' => 2, 'name' => 'Bunde', 'email' => 'user_2@secured.local', 'expected' => true],
            ['id' => 3, 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'expected' => false],
            ['id' => 456, 'name' => 'wyclife bunde', 'email' => 'someone@example.com', 'expected' => true],
        ];

        foreach ($cases as $case) {
            $actual = UsersModel::isProtectedUser((int) $case['id'], $case['name'], $case['email']);
            if ($actual !== $case['expected']) {
                throw new RuntimeException(
                    'Protected-user check failed for id=' . $case['id'] . ' name=' . $case['name'] . ' expected=' . ($case['expected'] ? 'true' : 'false') . ' actual=' . ($actual ? 'true' : 'false')
                );
            }
        }

        echo "User protection checks passed.\n";
    }
}

UserProtectionTest::run();
