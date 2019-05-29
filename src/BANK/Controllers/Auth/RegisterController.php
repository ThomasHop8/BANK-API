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
use BANK\Controllers\Account\AccountController;

class RegisterController {

    protected $auth;
    protected $accountController;

    private $lastAddressID;
    private $lastLoginID;

    public function __construct(ContainerInterface $container)   {
        $this->db = $container->get('db');
        $this->auth = $container->get('auth');

        $this->$accountController = new AccountController($container);
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
      if(!$this->_insertAddressData($user))
        return '{"error": true, "message": "DB address insert fail."}';

      $this->lastAddressID = $this->db->lastInsertId();

      // Insert login data from user object, return error if failed
      if(!$this->_insertLoginUser($user))
        return '{"error": true, "message": "DB login insert fail."}';

      $this->lastLoginID = $this->db->lastInsertId();

      // Insert main user from user object, return error if failed
      if(!$this->_insertUser($user))
        return '{"error": true, "message": "DB main user insert fail."}';

      // Return success message and send password mail if all steps are completed without errors
      $this->_sendPasswordMail($user->email, $user->fullname, $user->password);
      $this->$accountController->createAccount(1, $this->lastLoginID);

      return '{"success": true, "message": "Insert successfull!"}';
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
    private function _insertAddressData($user) {
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
    private function _insertLoginUser($user) {
      if(!$user)
        return false;

      $loginUserSQL = "INSERT INTO Gebruiker (UserID, Email, Wachtwoord, Token, LastLogin) VALUES (NULL, :email, :password, NULL, CURRENT_TIMESTAMP)";
      $hashedPassword = password_hash($user->password, PASSWORD_DEFAULT);

      return $this->db->prepare($loginUserSQL)->execute([':email' => $user->email, ':password' => $hashedPassword]);
    }

    /**
     * Method for creating a new 'particulier' user
     * @param  JSONObject $user object of user
     * @return Boolean return success
     */
    private function _insertUser($user) {
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

    /**
     * Method for creating a new 'company' user
     * @param  JSONObject $company object of user
     * @return Boolean return success
     */
    private function _insertCompany($company) {
      // Check if company object exists
      if(!$company)
        return false;

      // Prepare SQL statement as string
      $companySQL = "INSERT INTO Bedrijf (KVK, Bedrijfsnaam, GegevensID, UserID) VALUES (:kvk, :compname, :gegevensID, :login)";

      // Prepare statement in PDO
      $stmt = $this->db->prepare($companySQL);

      // Bind all the parameters starting with :
      $stmt->bindParam("kvk", $company->kvk);
      $stmt->bindParam("compname", $company->compname);
      $stmt->bindParam("gegevensID", $this->lastAddressID);
      $stmt->bindParam("login", $this->lastLoginID);

      // Return bool
      return $stmt->execute();
    }

    /**
     * Method for sending a styled email to the user
     * @param  String $email email of user
     * @param  String $username name of user
     * @param  String $password password of user
     */
    private function _sendPasswordMail($email, $username, $password) {
      $subject = 'Uw registratie bij Hoekbank';
      $headers = 'From: info@hoekbank.tk' . "\r\n" .
      'Reply-To: info@hoekbank.tk' . "\r\n" .
      'Return-Path: info@hoekbank.tk' . "\r\n" .
      'MIME-Version: 1.0' . "\r\n" .
      'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
      'Organization: Hoekbank' . "\r\n" .
      'X-Mailer: PHP/' . phpversion();

      $message = file_get_contents("../public/mail/password.html");
      $vars = array(
        '{{username}}' => $username,
        '{{password}}' => $password,
      );

      mail($email, $subject, strtr($message, $vars), $headers);
    }
}
