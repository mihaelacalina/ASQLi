<?php

namespace ASQLi;


/**
 * This is an account used to connect to a database.
 * 
 * @see FromEnv For secure retrieval of account from environment variables.
 * @see FromInfo For loading info directly.
 * @see AsRoot For a default root account with no password.
 */
class User {
    protected string $Name;
    protected string $Password;


    protected function __construct(#[\SensitiveParameter] string $Username, #[\SensitiveParameter] string $Password) {
        $this -> Password = $Password;
        $this -> Name = $Username;
    }

    /**
     * Returns an array containing the username and the password.
     * 
     * @return string[] The array containing the credentials
     */
    public function GetCredentials(): array {
        return [$this -> Name, $this -> Password];
    }

    /**
     * Creates an User object with the username and password read from the system environment variables.
     * 
     * @param string $User The name of the evironment variable that contains the username.
     * @param string $Pass The name of the evironment variable that contains the password.
     * 
     * @throws MissingEnvException If an environment variable is missing.
     * 
     * @return User The user object.
     */
    public static function FromEnv(string $User = "DB_USER", string $Pass = "DB_PASS"): User {
        $Username = getenv($User);
        $Password = getenv($Pass);

        if ($Username === false) {
            throw new MissingEnvException("Unable to get environment variable for username");
        }

        if ($Password === false) {
            throw new MissingEnvException("Unable to get environment variable for password");
        }

        return new static($Username, $Password);
    }

    /**
     * Creates an User object with the username and password read from the system environment variables.
     * 
     * @param string $Username The username.
     * @param string $Password The password.
     * 
     * @return User The user object.
     */
    public static function FromInfo(#[\SensitiveParameter] string $Username, #[\SensitiveParameter] string $Password): User {
        return new static($Username, $Password);
    }

    /**
     * Returns an account with the username set to "root" and no password.
     * 
     * Note:
     *  It would probably be ideal not to use the default root account in a production environment.
     * 
     * @return User The user.
     */
    public static function AsRoot(): User {
        return new static("root", "");
    }
}