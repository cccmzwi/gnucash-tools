<?php

require "AbstractTransaction.php";

class MVBTransaction extends AbstractTransaction
{

	public function __construct($type = 'Cash', $account = 'Aktiva:Imported')
	{
		$this->setType($type);
		$this->setAccount($account);
	}


	public function parseData($data)
	{
		$this->setDate($data[0]);
		$this->setAmount((($data[9] == 'H') ? "" : "-") . $data[8]);

		$this->setName($data[3]);
		$this->setMemo($data[6]);
	}

	public function getParsedTransaction()
	{
		return
			$this->getParsedType() . "\n" .
			$this->getParsedDate() . "\n" .
			$this->getParsedAccount() . "\n" .
			$this->getParsedAmount() . "\n" .
			$this->getParsedMemo() . "\n" . 
			'^' . "\n";
	}

	public function setName($name)
	{
		parent::setName(
			mb_convert_case($name, MB_CASE_TITLE)
		);
	}

	public function setMemo($memo)
	{
		parent::setMemo(
			mb_convert_case(str_replace("\n", " ", $memo), MB_CASE_TITLE)
		);
	}
}

?>
