<?php
/**
 * AccountController
 *
 * Controller Class containing all methods regarding a bank account
 *
 * @copyright  Thomas Hopstaken
 * @since      13 - 04 - 2019
 */

namespace BANK\Controllers\Account;

use PDO;
use Interop\Container\ContainerInterface;
use \Firebase\JWT\JWT;

class AccountController {

    protected $auth;

    private $accountNr;

    public function __construct(ContainerInterface $container)   {
        $this->db = $container->get('db');
        $this->auth = $container->get('auth');
    }

    function new($request, $response, $args) {
      $emp = $request->getParsedBody()['account'];

      // Check if account object is provided
      if(!$emp)
        return '{"error": true, "message": "No account object provided."}';

      $account = json_decode($emp);

      // Check if auth token matches user, return 401 if not
      if(!$this->auth->authenticateUser($request, $account->user, $response))
        return $response->withStatus(401);

      // Create account in DB, return error if failed
      if(!$this->createAccount($account->type, $account->user))
        return '{"error": true, "message": "DB account insert fail."}';

      return '{"success": true, "message": "Account created successfully!"}';
    }

    function authorize($request, $response, $args) {
      $emp = $request->getParsedBody()['account'];

      // Check if account object is provided
      if(!$emp)
        return '{"error": true, "message": "No account object provided."}';

      $account = json_decode($emp);

      // Check if auth token matches user and has an employee email, return 401 if not
      if(!$this->auth->authenticateEmployee($request, $response))
        return $response->withStatus(401);

      // Create authorization in DB, return error if failed
      if(!$this->insertAuthorization($account->user, $account->accountNr, $account->role))
        return '{"error": true, "message": "DB auth insert fail."}';

      return '{"success": true, "message": "Account authorized successfully!"}';
    }

    public function getAll($request, $response) {
      $emp = $request->getParsedBody();
      $user = $emp['user'];

      // Check if account object is provided
      if(!$user)
        return '{"error": true, "message": "No user provided."}';

      // Check if auth token matches user, return 401 if not
      if(!$this->auth->authenticateUser($request, $user, $response))
        return $response->withStatus(401);

      return $this->_getUserAccounts($user);
    }

    public function getEmail($request, $response) {
      $emp = $request->getParsedBody();
      $user = $emp['user'];

      // Check if account object is provided
      if(!$user)
        return '{"error": true, "message": "No user provided."}';

      // Check if auth token matches user and has an employee email, return 401 if not
      if(!$this->auth->authenticateEmployee($request, $response))
        return $response->withStatus(401);

      $sql = "SELECT UserID FROM Gebruiker WHERE Email = :email";
      $stmt = $this->db->prepare($sql);
      $stmt->execute(['email' => $user]);
      $userID = $stmt->fetch()['UserID'];

      if(!$userID)
        return '{"error": true, "message": "Email not valid"}';

      return $this->_getUserAccounts($userID);
    }



    public function createAccount($type, $user) {
      $accountSQL = "INSERT INTO Rekening (RekeningNr, TypeID, UserID, Saldo) VALUES (:reknr, :type, :user, '0')";
      $stmt = $this->db->prepare($accountSQL);
      $this->accountNr = $this->_generateAccountNumber();

      $stmt->bindParam("reknr", $this->accountNr);
      $stmt->bindParam("type", $type);
      $stmt->bindParam("user", $user);

      if($stmt->execute()) {
        return $this->insertAuthorization($user, $this->accountNr, 1);
      } else {
        return 0;
      }
    }

    public function insertAuthorization($user, $rekNr, $role) {
      $authSQL = "INSERT INTO Machtiging (MachtigingID, UserID, RekNr, RoleID) VALUES (NULL, :user, :reknr, :role)";
      $stmt = $this->db->prepare($authSQL);

      $stmt->bindParam("user", $user);
      $stmt->bindParam("reknr", $rekNr);
      $stmt->bindParam("role", $role);

      return $stmt->execute();
    }

    /**
     * Method for creating a new account auth role
     * @param  String $rolnaam string of role name
     * @return Boolean return success
     */
    public function insertAuthRole($rolnaam) {
      // Check if a role name is provided
      if(!$rolnaam)
        return false;

      // Prepare SQL statement as string
      $authRoleSQL = "INSERT INTO MachtigingRole (RoleID, RoleNaam) VALUES (NULL, :role)";

      // Prepare statement in PDO
      $stmt = $this->db->prepare($authRoleSQL);

      // Bind all the parameters starting with :
      $stmt->bindParam("role", $rolnaam);

      // Return bool
      return $stmt->execute();
    }



    /**
     * Method for returning array of user accounts
     * @param  Int $user user id
     * @return JSON return array containing all user accounts
     */
    private function _getUserAccounts($user) {
      $accountSQL = "SELECT Rekening.RekeningNr, Rekening.UserID, Rekening.Saldo, Particulier.`Volledige Naam`, Bedrijf.Bedrijfsnaam, RekeningType.TypeNaam FROM Machtiging JOIN Rekening ON Rekening.RekeningNr = Machtiging.RekNr JOIN RekeningType ON Rekening.TypeID = RekeningType.TypeID LEFT JOIN Particulier ON Rekening.UserID = Particulier.UserID LEFT JOIN Bedrijf ON Rekening.UserID = Bedrijf.UserID WHERE Machtiging.UserID = :user";
      $stmt = $this->db->prepare($accountSQL);
      $stmt->execute([':user' => $user]);

      return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Method for generating a valid random account number
     * @return String return validated random number
     */
    private function _generateAccountNumber() {
      $min = 800000000;
      $max = 899999999;
      $randNum = rand($min, $max);

      while(!$this->_testAccountNumber($randNum)) {
        $randNum = rand($min, $max);
      }

      return '0' . $randNum;
    }

    /**
     * Method for testing the generated account number
     * @param  Int $accNum account number
     * @return Boolean return if test passes or fails
     */
    private function _testAccountNumber($accNum){
      $csom = 0;
      $pos = 9;

      for($i = 0; $i < strlen($accNum); $i++) {
        // Substring every character of number string
        $num = substr($accNum, $i, 1);

        // Check if value is a number
        if(is_numeric($num)) {
          // Calculate sum of number and position
          $csom += $num * $pos;
          // Switching to next position
          $pos -= 1;
        }
      }

      return( ($pos > 1) && ($pos < 7) || !($pos || $csom % 11) );
    }
}
