<?php
/**
 * LINK Mobility SMS Notifications
 *
 * Sends transactional SMS notifications through the LINK Mobility messaging
 * service.
 *
 * @package Linkmobility\Notifications\Test\Integration\_files
 * @author Joseph Leedy <joseph@wagento.com>
 * @author Yair García Torres <yair.garcia@wagento.com>
 * @copyright Copyright (c) LINK Mobility (https://www.linkmobility.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

use Linkmobility\Notifications\Model\SmsType;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var \Linkmobility\Notifications\Model\SmsType $smsType */
$smsType = $objectManager->create(SmsType::class);

$smsType->setData([
    'name' => 'Test Notificatiom',
    'description' => 'Test notification',
    'is_active' => 1,
]);
$smsType->isObjectNew(true);
$smsType->save();

return $smsType;
