<?php
/**
 * UserController
 *
 * Controller Class containing all methods regarding a existing user.
 *
 * @copyright  Thomas Hopstaken
 * @since      18 - 03 - 2019
 */

namespace BANK\Controllers\User;

use PDO;
use Interop\Container\ContainerInterface;
use \Firebase\JWT\JWT;

class UserController {

    protected $auth;

    public function __construct(ContainerInterface $container)   {
        $this->db = $container->get('db');
        $this->auth = $container->get('auth');
    }

    /**
     * Method for logging in the user
     * @param  ArrayObject $request POST API object
     * @return JSON return
     */
    function login($request) {
        $emp = $request->getParsedBody();
        $sql = "SELECT * FROM Gebruiker WHERE email = '" . $emp['email'] . "'";
        $userData = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC)[0];

        if(!password_verify($emp['password'], $userData['Wachtwoord']))
          return '{"error": true, "message": "Wrong username & password combination"}';

        unset($userData['Wachtwoord']);
        unset($userData['Token']);

        $token = $this->auth->generateToken($userData['UserID']);
        $this->__updateUserToken($userData['Email'], $token);
        $userData['Token'] = $token;

        echo json_encode($userData);
    }


    /**
     * Method for updating the new user auth token and timestamp
     * @param  Integer $user userID
     * @param  Integer $token user token
     * @return JSON return
     */
    private function __updateUserToken($user, $token) {
      $updatesql = "UPDATE Gebruiker SET Token = '" . $token . "', LastLogin = CURRENT_TIMESTAMP WHERE email = '" . $user . "';";
      $this->db->query($updatesql);
    }
}
