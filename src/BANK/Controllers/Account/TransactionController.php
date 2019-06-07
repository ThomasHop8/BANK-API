<?php
/**
 * TransactionController
 *
 * Controller Class containing all methods regarding account transactions
 *
 * @copyright  Thomas Hopstaken
 * @since      15 - 04 - 2019
 */

namespace BANK\Controllers\Account;

use PDO;
use Interop\Container\ContainerInterface;
use \Firebase\JWT\JWT;

class TransactionController {

    protected $auth;

    public function __construct(ContainerInterface $container)   {
        $this->db = $container->get('db');
        $this->auth = $container->get('auth');
    }

    function new($request, $response, $args) {
    }

    /**
     * Method for fetching all account transactions of single user
     * @author Thomas Hopstaken
     * @param  ArrayObject $request POST API request object
     * @param  ArrayObject $response POST API response object
     * @return JSON return array containing all transactions
     */
    public function getAll($request, $response) {
      $emp = $request->getParsedBody();

      // Check if account object is provided
      if(!$emp)
        return '{"error": true, "message": "No account object provided."}';

      // Check if auth token matches user, return 401 if not
      if(!$this->auth->authenticateUser($request, $emp['user'], $response))
        return $response->withStatus(401);

      // Select tranactions from DB
      return $this->_getAccountTransactions($emp['accountNr']);
    }



    /**
     * Method for returning array of account transactions
     * @author Thomas Hopstaken
     * @param  Int $reknr account number
     * @return JSON return array containing all transactions
     */
    private function _getAccountTransactions($reknr) {
      $accountSQL = "SELECT Transactie.Bedrag, Transactie.TransTime, part1.`Volledige Naam` AS InNaam, part2.`Volledige Naam` AS UitNaam, comp1.Bedrijfsnaam AS InBedrijf, comp2.Bedrijfsnaam AS UitBedrijf FROM Transactie JOIN TransIn ON TransIn.TransID = Transactie.TransID JOIN TransOut ON TransOut.TransID = Transactie.TransID JOIN Rekening AS rek1 ON rek1.RekeningNr = TransIn.RekNr JOIN Rekening AS rek2 ON rek2.RekeningNr = TransOut.RekNr LEFT JOIN Particulier AS part1 ON rek1.UserID = part1.UserID LEFT JOIN Particulier AS part2 ON rek2.UserID = part2.UserID LEFT JOIN Bedrijf AS comp1 ON rek1.UserID = comp1.UserID LEFT JOIN Bedrijf AS comp2 ON rek2.UserID = comp2.UserID WHERE TransIn.RekNr = :reknr OR TransOut.RekNr = :reknr";
      $stmt = $this->db->prepare($accountSQL);
      $stmt->execute([':reknr' => $reknr]);

      return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
