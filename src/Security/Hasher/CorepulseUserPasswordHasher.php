<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace CorepulseBundle\Security\Hasher;

use Pimcore\Model\DataObject\ClassDefinition\Data\Password;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Security\Hasher\AbstractUserAwarePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\CheckPasswordLengthTrait;
use Symfony\Component\Security\Core\Exception\RuntimeException;

/**
 * @internal
 *
 * @method Concrete getUser()
 */
class CorepulseUserPasswordHasher extends AbstractUserAwarePasswordHasher
{
    use CheckPasswordLengthTrait;

    protected string $fieldName;

    /**
     * If true, the user password hash will be updated if necessary.
     *
     */
    // protected bool $updateHash = true;

    public function __construct(string $fieldName = 'password')
    {
        $this->fieldName = $fieldName;
    }

    public function hash(string $plainPassword, string $salt = null): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new BadCredentialsException(sprintf('Password exceeds a maximum of %d characters', static::MAX_PASSWORD_LENGTH));
        }

        return self::getPasswordHash($this->getUser()->getUsername(), $plainPassword);
    }

    /**
     *
     * @throws RuntimeException
     */

    public function verify(string $hashedPassword, string $plainPassword, ?string $salt = null): bool
    {
        if ($this->isPasswordTooLong($hashedPassword)) {
            return false;
        }

        $user = $this->getUser();

        if (!$user->getPassword()) {
            // do not allow logins for users without a password
            return false;
        }

        $password = self::preparePlainTextPassword($user->getUsername(), $plainPassword);

        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        // $config = Config::getSystemConfiguration()['security']['password'];

        if (password_needs_rehash($user->getPassword(), PASSWORD_BCRYPT, ['cost' => 12])) {
            $user->setPassword(self::getPasswordHash($user->getUsername(), $password));
            $user->save();
        }

        return true;
    }

    private static function preparePlainTextPassword(string $username, string $plainTextPassword): string
    {
        return md5($username . ':corepulse:' . $plainTextPassword);
    }

    public static function getPasswordHash(string $username, string $plainTextPassword): string
    {
        $password = self::preparePlainTextPassword($username, $plainTextPassword);
        // $config = Config::getSystemConfiguration()['security']['password'];

        if ($hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])) {
            return $hash;
        }

        throw new \Exception('Unable to create password hash for user: ' . $username);
    }
}
