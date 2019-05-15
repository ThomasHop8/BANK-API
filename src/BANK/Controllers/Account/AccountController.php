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
      if(!$this->__createAccount($account->type, $account->user))
        return '{"error": true, "message": "DB account insert fail."}';

      return '{"success": true, "message": "Account created successfully!"}';
    }

    public function __createAccount($type, $user) {
      $accountSQL = "INSERT INTO Rekening (RekeningNr, TypeID, UserID, Saldo) VALUES (:reknr, :type, :user, '0')";
      $stmt = $this->db->prepare($accountSQL);

      $stmt->bindParam("reknr", $this->__generateAccountNumber());
      $stmt->bindParam("type", $type);
      $stmt->bindParam("user", $user);

      return $stmt->execute();
    }

    /**
     * Method for generating a valid random account number
     * @return String return validated random number
     */
    private function __generateAccountNumber() {
      $min = 800000000;
      $max = 899999999;
      $randNum = rand($min, $max);

      while(!$this->__testAccountNumber($randNum)) {
        $randNum = rand($min, $max);
      }

      return '0' . $randNum;
    }

    /**
     * Method for testing the generated account number
     * @param  Int $accNum account number
     * @return Boolean return if test passes or fails
     */
    private function __testAccountNumber($accNum){
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
