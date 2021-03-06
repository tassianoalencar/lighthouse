<?php

namespace Nuwave\Lighthouse\Subscriptions;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\AST;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\SubscriptionException;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Serializable;

class Subscriber implements Serializable
{
    public const MISSING_OPERATION_NAME = 'Must pass an operation name when using a subscription.';

    /**
     * A unique key for the subscriber.
     *
     * @var string
     */
    public $channel;

    /**
     * The topic subscribed to.
     *
     * @var string
     */
    public $topic;

    /**
     * The contents of the query.
     *
     * @var \GraphQL\Language\AST\DocumentNode
     */
    public $query;

    /**
     * The name of the queried operation.
     *
     * @var string
     */
    public $operationName;

    /**
     * The root element of the query.
     */
    public $root;

    /**
     * The args passed to the subscription query.
     *
     * @var mixed[]
     */
    public $args;

    /**
     * The context passed to the query.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public $context;

    /**
     * @param  mixed[]  $args
     *
     * @throws \Nuwave\Lighthouse\Exceptions\SubscriptionException
     */
    public function __construct(
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo
    ) {
        $operationName = $resolveInfo->operation->name;

        // TODO remove that check and associated tests once graphql-php covers that validation https://github.com/webonyx/graphql-php/pull/644
        if (! $operationName) {
            throw new SubscriptionException(self::MISSING_OPERATION_NAME);
        }
        $this->operationName = $operationName->value;

        $this->channel = self::uniqueChannelName();
        $this->args = $args;
        $this->context = $context;

        $documentNode = new DocumentNode([]);
        $documentNode->definitions = $resolveInfo->fragments;
        $documentNode->definitions[] = $resolveInfo->operation;
        $this->query = $documentNode;
    }

    /**
     * Unserialize subscription from a JSON string.
     *
     * @param  string  $subscription
     * @return $this
     */
    public function unserialize($subscription): self
    {
        $data = json_decode($subscription, true);

        $this->channel = $data['channel'];
        $this->topic = $data['topic'];
        $this->query = AST::fromArray(
            unserialize($data['query'])
        );
        $this->operationName = $data['operation_name'];
        $this->args = $data['args'];
        $this->context = $this->contextSerializer()->unserialize(
            $data['context']
        );

        return $this;
    }

    /**
     * Convert this into a JSON string.
     */
    public function serialize(): string
    {
        return json_encode([
            'channel' => $this->channel,
            'topic' => $this->topic,
            'query' => serialize(
                AST::toArray($this->query)
            ),
            'operation_name' => $this->operationName,
            'args' => $this->args,
            'context' => $this->contextSerializer()->serialize($this->context),
        ]);
    }

    /**
     * Set root data.
     *
     * @return $this
     */
    public function setRoot($root): self
    {
        $this->root = $root;

        return $this;
    }

    /**
     * Generate a unique private channel name.
     */
    public static function uniqueChannelName(): string
    {
        return 'private-lighthouse-'.Str::random(32).'-'.time();
    }

    protected function contextSerializer(): ContextSerializer
    {
        return app(ContextSerializer::class);
    }
}
