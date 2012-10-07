<?php

class Ruler
{
	private static $i = 0;
	private static $rules = array();
	private static $applied = array();

	public static function addRule(Rule $rule)
	{
		self::$rules[] = $rule;
	}

	public static function applyRules(AbstractTransaction &$transaction)
	{
		self::reset();
		$matched = false;

		while ($rule = self::next()) {
			$result = $rule->applyRule($transaction);

			if ($result) {
				if (empty(self::$applied[$rule->getId()])) {
					self::$applied[$rule->getId()] = 1;
				} else {
					self::$applied[$rule->getId()]++;
				}
				$matched = true;
			} else {
//				$transaction->setMemo($transaction->getName() . ": " . $transaction->getMemo());
			}

		}
		return $matched;
	}

	public static function printStats()
	{
		self::reset();

		$appliedRules = array();
		$unappliedRules = array();

		while ($rule = self::next()) {
			if (empty(self::$applied[$rule->getId()])) {
				$unappliedRules[] = $rule->getId();
			} else {
				$appliedRules[$rule->getId()] = self::$applied[$rule->getId()];
			}
		}

		echo "Applied Rules:\n";
		foreach ($appliedRules as $name => $times) {
			echo $name, " [", $times, " time(s)]\n";
		}
		
		echo "\nUnapplied Rules:\n";
		foreach ($unappliedRules as $name) {
			echo $name, "\n";
		}
	}

	private static function reset()
	{
		self::$i = 0;
	}

	private static function next()
	{
		if (count(self::$rules) <= self::$i) {
			return false;
		}

		return self::$rules[self::$i++];
	}
}

?>
