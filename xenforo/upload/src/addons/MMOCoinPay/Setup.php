<?php

namespace MMOCoinPay;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		$this->db()->insert('xf_payment_provider',
			[
				'provider_id' => 'mmocoinpay',
				'provider_class' => 'MMOCoinPay:MmoCoinPay',
				'addon_id' => 'MMOCoinPay',
			], 'provider_id');
	}

	public function uninstallStep1()
	{
		$this->db()->delete('xf_payment_provider', "provider_class LIKE 'MMOCoinPay%'");
	}
}
