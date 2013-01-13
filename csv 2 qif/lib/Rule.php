<?php

class Rule
{
	// Rule ID so it can be reported when it was applied
	private $id = null;
	private static $autoId = 0;

	private $namePattern = null;
	private $nameReplace = null;
	private $accountPattern = null;
	private $accountReplace = null;
	private $amountPattern = null;
	private $amountReplace = null;
	private $memoPattern = null;
	private $memoReplace = null;

	public function __construct($id = null)
	{
		self::$autoId++;
		$this->id = $id;

		if (empty($this->id)) {
			$this->id = self::$autoId;
		}
	}

	public function getId()
	{
		return $this->id;
	}


	public function setNameRule($pattern, $replace = null)
	{
		$this->namePattern = $pattern;
		$this->nameReplace = $replace;
	}

	public function setAccountRule($pattern, $replace = null)
	{
		$this->accountPattern = $pattern;
		$this->accountReplace = $replace;
	}

	public function setAmountRule($pattern, $replace = null)
	{
		$this->amountPattern = $pattern;
		$this->amountReplace = $replace;
	}

	public function setMemoRule($pattern, $replace = null)
	{
		$this->memoPattern = $pattern;
		$this->memoReplace = $replace;
	}


	/*
	 * 1: pattern  = null & replace  = rull  =>  rule doesnt care
	 * 2: pattern != null & replace  = null  =>  rule cares but that is not what will be changed
	 * 3: pattern  = null & replace != null  =>  rule doesnt care but there is something to do
	 * 4: pattern != null & replace != null  =>  rule cares and there is something to do
	 */
	private function ruleApplies(AbstractTransaction &$transaction)
	{
		// If all rules are empty, there is no point in going any further.
		if (empty($this->namePattern) && empty($this->accountPattern) 
			&& empty($this->amountPattern) && empty($this->memoPattern)) {
			trigger_error("Can't apply rule without any patterns. Check rule \"" . $this->getId() . '".');
			return false;
		}

		// Eighter the pattern is empty, or it is /not/ emtpy /and/ matches. 
		// Done for all patterns, this computes whether this rule applies or not.
		return 
			(empty($this->namePattern) ||
			(!empty($this->namePattern) && preg_match($this->namePattern, $transaction->getName()))) &&
			(empty($this->accountPattern) ||
			(!empty($this->accountPattern) && preg_match($this->accountPattern, $transaction->getAccount()))) &&
			(empty($this->amountPattern) ||
			(!empty($this->amountPattern) && preg_match($this->amountPattern, $transaction->getAmount()))) &&
			(empty($this->memoPattern) ||
			(!empty($this->memoPattern) && preg_match($this->memoPattern, $transaction->getMemo())));
	}

	public function applyRule(AbstractTransaction &$transaction)
	{
		if (!$this->ruleApplies($transaction)) {
			return false;
		}

		if (!empty($this->nameReplace)) {
			if (!empty($this->namePattern)) {
				$transaction->setName(preg_replace($this->namePattern, $this->nameReplace, $transaction->getName()));
			} else {
				$transaction->setName($this->nameReplace);
			}
		}

		if (!empty($this->accountReplace)) {
			if (!empty($this->accountPattern)) {
				$transaction->setAccount(preg_replace($this->accountPattern, $this->accountReplace, $transaction->getAccount()));
			} else {
				$transaction->setAccount($this->accountReplace);
			}
		}
		if (!empty($this->amountReplace)) {
			if (!empty($this->amountPattern)) {
				$transaction->setAmount(preg_replace($this->amountPattern, $this->amountReplace, $transaction->getAmount()));
			} else {
				$transaction->setAmount($this->amountReplace);
			}
		}
		if (!empty($this->memoReplace)) {
			if (!empty($this->memoPattern)) {
				$transaction->setMemo(preg_replace($this->memoPattern, $this->memoReplace, $transaction->getMemo()));
			} else {
				$transaction->setMemo($this->memoReplace);
			}
		}

		return true;
	}
}

?>
