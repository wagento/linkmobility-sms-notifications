<?php
/**
 * LINK Mobility SMS Notifications
 *
 * Sends transactional SMS notifications through the LINK Mobility messaging
 * service.
 *
 * @package Linkmobility\Notifications\Gateway\Factory
 * @author Joseph Leedy <joseph@wagento.com>
 * @author Yair García Torres <yair.garcia@wagento.com>
 * @copyright Copyright (c) LINK Mobility (https://www.linkmobility.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */
declare(strict_types=1);

namespace Linkmobility\Notifications\Gateway\Factory;

use Linkmobility\Notifications\Gateway\Entity\Message;
use Linkmobility\Notifications\Gateway\Entity\MessageInterface;

/**
 * Message Entity Factory
 *
 * @package Linkmobility\Notifications\Gateway\Factory
 * @author Joseph Leedy <joseph@wagento.com>
 */
final class MessageFactory
{
    public function create(
        string $source = null,
        string $destination = null,
        string $userData = null,
        string $platformId = null,
        string $platformPartnerId = null
    ): MessageInterface {
        return new Message($source, $destination, $userData, $platformId, $platformPartnerId);
    }
}