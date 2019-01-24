<?php
/**
 * LINK Mobility SMS Notifications
 *
 * Sends transactional SMS notifications through the LINK Mobility messaging
 * service.
 *
 * @package Linkmobility\Notifications\Controller\SmsNotifications
 * @author Joseph Leedy <joseph@wagento.com>
 * @author Yair García Torres <yair.garcia@wagento.com>
 * @copyright Copyright (c) LINK Mobility (https://www.linkmobility.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

declare(strict_types=1);

namespace Linkmobility\Notifications\Controller\SmsNotifications;

use Linkmobility\Notifications\Api\Data\SmsSubscriptionInterfaceFactory;
use Linkmobility\Notifications\Api\SmsSubscriptionRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Manage SMS Subscriptions POST Action Controller
 *
 * @package Linkmobility\Notifications\Controller\SmsNotifications
 * @author Joseph Leedy <joseph@wagento.com>
 */
class ManagePost extends Action implements ActionInterface, CsrfAwareActionInterface
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var \Linkmobility\Notifications\Api\SmsSubscriptionRepositoryInterface
     */
    private $smsSubscriptionRepository;
    /**
     * @var \Linkmobility\Notifications\Api\Data\SmsSubscriptionInterfaceFactory
     */
    private $smsSubscriptionFactory;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        LoggerInterface $logger,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SmsSubscriptionRepositoryInterface $smsSubscriptionRepository,
        SmsSubscriptionInterfaceFactory $smsSubscriptionFactory
    ) {
        parent::__construct($context);

        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->smsSubscriptionRepository = $smsSubscriptionRepository;
        $this->smsSubscriptionFactory = $smsSubscriptionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $customerId = $this->customerSession->getCustomerId();
        $selectedSmsTypes = array_keys($this->getRequest()->getParam('sms_types', []));

        $resultRedirect->setPath('*/*/manage');

        if ($customerId === null) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while saving your text notification preferences.')
            );
            $this->logger->critical(__('Could not get ID of customer to save SMS subscriptions for.'));

            return $resultRedirect;
        }

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('customer_id', $customerId)->create();
        $subscribedSmsTypes = $this->smsSubscriptionRepository->getList($searchCriteria)->getItems();

        if (count($subscribedSmsTypes) > 0) {
            $this->removeSubscriptions($subscribedSmsTypes, $selectedSmsTypes, $customerId);

            $selectedSmsTypes = array_diff($selectedSmsTypes, array_column($subscribedSmsTypes, 'sms_type'));
        }

        if (count($selectedSmsTypes) > 0) {
            $this->createSubscriptions($selectedSmsTypes, $customerId);
        }

        return $resultRedirect;
   }

    /**
     * {@inheritdoc}
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $resultRedirect->setPath('*/*/manage');

        return new InvalidRequestException($resultRedirect, [__('Invalid Form Key. Please refresh the page.')]);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }

    /**
     * @param \Linkmobility\Notifications\Model\SmsSubscription[] $subscribedSmsTypes
     * @param string[] $selectedSmsTypes
     * @param string|int $customerId
     */
    private function removeSubscriptions(array &$subscribedSmsTypes, array $selectedSmsTypes, $customerId): int
    {
        $removedSubscriptions = 0;

        foreach ($subscribedSmsTypes as $key => $subscribedSmsType) {
            if (in_array($subscribedSmsType->getSmsType(), $selectedSmsTypes, true)) {
                continue;
            }

            try {
                $this->smsSubscriptionRepository->deleteById((int)$subscribedSmsType->getId());

                ++$removedSubscriptions;

                unset($subscribedSmsTypes[$key]);
            } catch (NoSuchEntityException | CouldNotDeleteException $e) {
                $this->logger->critical(
                    __('Could not delete SMS subscription for customer. Error: %1', $e->getMessage()),
                    [
                        'customer_id' => $customerId,
                        'sms_type' => $subscribedSmsType->getSmsType(),
                        'area' => 'frontend'
                    ]
                );
            }
        }

        $remainingSubscriptions = array_diff(array_column($subscribedSmsTypes, 'sms_type'), $selectedSmsTypes);
        $remainingSubscriptionCount = count($remainingSubscriptions) - $removedSubscriptions;

        if ($remainingSubscriptionCount > 0 && $removedSubscriptions > 0) {
            if ($remainingSubscriptionCount === 1) {
                $errorMessage = __('You could not be unsubscribed from 1 text notification.');
            } else {
                $errorMessage = __('You could not be unsubscribed from %1 text notifications.', $remainingSubscriptionCount);
            }

            $this->messageManager->addErrorMessage($errorMessage);
        } elseif ($remainingSubscriptionCount > 0) {
            $this->messageManager->addErrorMessage(
                __('You could not be unsubscribed from the selected text notifications.')
            );
        }

        if ($removedSubscriptions === 1) {
            $this->messageManager->addSuccessMessage(__('You have been unsubscribed from 1 text notification.'));
        }

        if ($removedSubscriptions > 1) {
            $this->messageManager->addSuccessMessage(
                __('You have been unsubscribed from %1 text notifications.', $removedSubscriptions)
            );
        }

        return $removedSubscriptions;
    }

    /**
     * @param string[] $selectedSmsTypes
     * @param string|int $customerId
     */
    private function createSubscriptions(array $selectedSmsTypes, $customerId): int
    {
        $createdSubscriptions = 0;

        foreach ($selectedSmsTypes as $smsType) {
            /** @var \Linkmobility\Notifications\Api\Data\SmsSubscriptionInterface $smsSubscription */
            $smsSubscription = $this->smsSubscriptionFactory->create();

            $smsSubscription->setCustomerId((string)$customerId);
            $smsSubscription->setSmsType($smsType);

            try {
                $this->smsSubscriptionRepository->save($smsSubscription);

                ++$createdSubscriptions;
            } catch (CouldNotSaveException $e) {
                $this->logger->critical(
                    __('Could not subscribe customer to SMS notification. Error: %1', $e->getMessage()),
                    [
                        'customer_id' => $customerId,
                        'sms_type' => $smsType,
                        'area' => 'frontend'
                    ]
                );
            }
        }

        $remainingSubscriptions = count($selectedSmsTypes) - $createdSubscriptions;

        if ($remainingSubscriptions > 0 && $createdSubscriptions > 0) {
            if ($remainingSubscriptions === 1) {
                $errorMessage = __('You could not be subscribed to 1 text notification.');
            } else {
                $errorMessage = __('You could not be subscribed to %1 text notifications.', $remainingSubscriptions);
            }

            $this->messageManager->addErrorMessage($errorMessage);
        } elseif ($remainingSubscriptions > 0) {
            $this->messageManager->addErrorMessage(
                __('You could not be subscribed to the selected text notifications.')
            );
        }

        if ($createdSubscriptions === 1) {
            $this->messageManager->addSuccessMessage(__('You have been subscribed to 1 text notification.'));
        }

        if ($createdSubscriptions > 1) {
            $this->messageManager->addSuccessMessage(
                __('You have been subscribed to %1 text notifications.', $createdSubscriptions)
            );
        }

        return $createdSubscriptions;
    }
}
