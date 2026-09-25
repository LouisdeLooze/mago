<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use Magento\Framework\AuthorizationInterface;
use MagoAssistant\Mago\Api\Skill\ActionInterface;
use MagoAssistant\Mago\Api\Tool\UpfrontGuidanceToolInterface;
use MagoAssistant\Mago\Service\Skills\AbstractSkill;

/**
 * Skill built from FakeAction instances that carries upfront guidance for the model
 */
final class FakeGuidedSkill extends AbstractSkill implements UpfrontGuidanceToolInterface
{
    /**
     * @param ActionInterface[] $actions keyed by action name
     */
    public function __construct(
        private readonly string $name,
        private readonly string $upfrontGuidance,
        AuthorizationInterface $authorization,
        array $actions
    ) {
        parent::__construct($authorization, $actions);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUpfrontGuidance(): string
    {
        return $this->upfrontGuidance;
    }

    protected function getBaseDescription(): string
    {
        return 'Fake ' . $this->name . '.';
    }
}
