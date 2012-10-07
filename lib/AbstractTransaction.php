<?php

abstract class AbstractTransaction
{
	private $type;
	private $account;
	private $name;
	private $amount;
	private $memo;
	protected $date;


	abstract function parseData($data);
	abstract function getParsedTransaction();


	public function setType($type)
	{
		$this->type = $type;
	}

	public function getType()
	{
		return $this->type;
	}

	protected function getParsedType()
	{
		return '!Type:' . $this->getType();
	}



	public function setAccount($account)
	{
		$this->account = $account;
	}

	public function getAccount()
	{
		return $this->account;
	}

	protected function getParsedAccount()
	{
		return 'L' . $this->getAccount();
	}



	public function setName($name)
	{
		$this->name = $name;
	}

	public function getName()
	{
		return $this->name;
	}

	protected function getParsedName()
	{
		return $this->getName();
	}



	public function setAmount($amount)
	{
		$this->amount = $amount;
	}

	public function getAmount()
	{
		return $this->amount;
	}

	protected function getParsedAmount()
	{
		return 'T' . $this->getAmount();
	}

	
	
	public function setMemo($memo)
	{
		$this->memo = $memo;
	}

	public function getMemo()
	{
		return $this->memo;
	}

	protected function getParsedMemo()
	{
		return 'M' . $this->getMemo();
	}



	public function setDate($date)
	{
		$this->date = $date;
	}

	public function getDate()
	{
		return $this->date;
	}

	protected function getParsedDate()
	{
		return 'D' . date("m/d/Y", strtotime($this->date));
	}
}

?>
