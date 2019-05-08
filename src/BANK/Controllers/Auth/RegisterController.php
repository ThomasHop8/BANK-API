<?php
/**
 * RegisterController
 *
 * Controller Class containing all methods for registering a new user.
 *
 * @copyright  Thomas Hopstaken
 * @since      18 - 03 - 2019
 */

namespace BANK\Controllers\Auth;

use PDO;
use Interop\Container\ContainerInterface;
use \Firebase\JWT\JWT;

class RegisterController {

    protected $auth;
    private $lastAddressID;
    private $lastLoginID;

    public function __construct(ContainerInterface $container)   {
        $this->db = $container->get('db');
        $this->auth = $container->get('auth');
    }

    /**
     * Method for registering new user
     * @param  ArrayObject $request POST API object
     * @return JSON return
     */
    public function register($request, $response, $args) {
      $emp = $request->getParsedBody()['user'];

      // Check if user object is provided
      if(!$emp)
        return '{"error": true, "message": "No user object provided."}';

      $user = json_decode($emp);

      // Check if auth token matches user and has an employee email, return 401 if not
      if(!$this->auth->authenticateEmployee($request, $response))
        return $response->withStatus(401);

      // Insert address data from user object, return error if failed
      if(!$this->__insertAddressData($user))
        return '{"error": true, "message": "DB address insert fail."}';

      $this->lastAddressID = $this->db->lastInsertId();

      // Insert login data from user object, return error if failed
      if(!$this->__insertLoginUser($user))
        return '{"error": true, "message": "DB login insert fail."}';

      $this->lastLoginID = $this->db->lastInsertId();

      // Insert main user from user object, return error if failed
      if(!$this->__insertUser($user))
        return '{"error": true, "message": "DB main user insert fail."}';

      // Return success message if all steps are completed without errors
      return '{"error": false, "message": "Insert succesfull!"}';
    }

    /**
     * Method for registering new DB user account
     * @return ECHO return database webpage
     */
    public function registerDB($request, $response, $args) {
      $emp = $request->getParsedBody();
      $user = $emp['username'];
      $password = $emp['password'];

      $sql = "CREATE USER '" . $user . "'@'localhost' IDENTIFIED BY '" . $password . "'; GRANT USAGE ON *.* TO '" . $user . "'@'localhost' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 5000 MAX_CONNECTIONS_PER_HOUR 25 MAX_UPDATES_PER_HOUR 5 MAX_USER_CONNECTIONS 1; GRANT SELECT, INSERT, UPDATE ON `BANK`.* TO '" . $user . "'@'localhost';";
      $stmt = $this->db->query($sql);
      echo '<script>window.location.href = "https://hoekbank.tk/phpmyadmin";</script>';
    }



    /**
     * Method for inserting user address data
     * @param  JSONObject $user object of user
     * @return Boolean return success
     */
    private function __insertAddressData($user) {
      if(!$user)
        return false;

      $addressSQL = "INSERT INTO AdresGegeven (GegevensID, Straatnaam, Huisnummer, Postcode, Woonplaats, Telefoon) VALUES (NULL, :straatnaam, :huisnr, :postcode, :woonplaats, :tel)";

      $stmt = $this->db->prepare($addressSQL);
      $stmt->bindParam("straatnaam", $user->straatnaam);
      $stmt->bindParam("huisnr", $user->huisnummer);
      $stmt->bindParam("postcode", $user->postcode);
      $stmt->bindParam("woonplaats", $user->woonplaats);
      $stmt->bindParam("tel", $user->telefoonnummer);

      return $stmt->execute();
    }

    /**
     * Method for creating a new login user
     * @param  JSONObject $user object of user
     * @return Boolean return success
     */
    private function __insertLoginUser($user) {
      if(!$user)
        return false;

      $loginUserSQL = "INSERT INTO Gebruiker (UserID, Email, Wachtwoord, Token, LastLogin) VALUES (NULL, :email, :password, NULL, CURRENT_TIMESTAMP)";
      $hashedPassword = password_hash($user->password, PASSWORD_DEFAULT);

      $stmt = $this->db->prepare($loginUserSQL);
      $stmt->bindParam("email", $user->email);
      $stmt->bindParam("password", $hashedPassword);

      return $stmt->execute();
    }

    /**
     * Method for creating a new 'particulier' user
     * @param  JSONObject $user object of user
     * @return Boolean return success
     */
    private function __insertUser($user) {
      if(!$user)
        return false;

      $userSQL = "INSERT INTO Particulier (BSN, `Volledige Naam`, GegevensID, UserID) VALUES (:bsn, :fullname, :address, :login)";

      $stmt = $this->db->prepare($userSQL);
      $stmt->bindParam("bsn", $user->bsn);
      $stmt->bindParam("fullname", $user->fullname);
      $stmt->bindParam("address", $this->lastAddressID);
      $stmt->bindParam("login", $this->lastLoginID);

      return $stmt->execute();
    }
}
