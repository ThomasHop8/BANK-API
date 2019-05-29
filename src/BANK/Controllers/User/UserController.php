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
        $email = $emp['email'];

        $userSQL = "SELECT * FROM Gebruiker WHERE email = :email";
        $stmt = $this->db->prepare($userSQL);
        $stmt->execute([':email' => $email]);

        $userData = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];

        if(!password_verify($emp['password'], $userData['Wachtwoord']))
          return '{"error": true, "message": "Wrong username & password combination"}';

        unset($userData['Wachtwoord']);
        unset($userData['Token']);

        $token = $this->auth->generateToken($userData['UserID']);
        $this->_updateUserToken($userData['Email'], $token);
        $userData['Token'] = $token;
        $userData['success'] = true;

        echo json_encode($userData);
    }

    function reject($request) {
      $emp = $request->getParsedBody();
      $idnum = $emp['idnum'];
      $reason = $emp['reason'];

      // Check if auth token matches user and has an employee email, return 401 if not
      if(!$this->auth->authenticateEmployee($request, $response))
        return $response->withStatus(401);

      $rejectSQL = "INSERT INTO Blocked (IdentificationNumber, Notitie, Timestamp) VALUES (:idnum, :reason, CURRENT_TIMESTAMP)";
      $stmt = $this->db->prepare($rejectSQL);

      return '{"error": ' . ($stmt->execute([':idnum' => $idnum, ':reason' => $reason]) != '' ? 'false': 'true') . '}';
    }


    /**
     * Method for updating the new user auth token and timestamp
     * @param  Integer $user userID
     * @param  Integer $token user token
     * @return JSON return
     */
    private function _updateUserToken($user, $token) {
      $updateTokenSQL = "UPDATE Gebruiker SET Token = :token, LastLogin = CURRENT_TIMESTAMP WHERE email = :user";
      $this->db->prepare($updateTokenSQL)->execute([':token' => $token, ':user' => $user]);
    }
}
