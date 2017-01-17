<?php

namespace Facile\CrossbarHTTPPublisherBundle\Publisher;

use Facile\CrossbarHTTPPublisherBundle\Exception\PublishRequestException;
use GuzzleHttp\Client;

/**
 * Class Publisher
 * @package Facile\CrossbarHTTPPublisherBundle\Publisher
 */
class Publisher
{
    /** @var \GuzzleHttp\Client */
    private $client;

    /** @var string */
    private $key;

    /** @var string */
    private $secret;

    /**
     * @param Client $client
     * @param $key
     * @param $secret
     */
    public function __construct(Client $client, $key, $secret)
    {
        $this->client = $client;
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * @param $topic string
     * @param $args array|null
     * @param $kwargs array|null
     * @return array JSON decoded response
     * @throws \Exception
     */
    public function publish($topic, array $args = null, array $kwargs = null)
    {
        $jsonBody = $this->prepareBody($topic, $args, $kwargs);

        $request = $this->client->createRequest(
            'POST',
            null,
            array(
                'body' => $jsonBody,
                'query' => $this->prepareSignature($jsonBody)
            )
        );

        try {
            $response = $this->client->send($request);
        } catch (\Exception $e) {
            throw new PublishRequestException($e->getMessage(), 500, $e);
        }

        return $response->json();
    }

    /**
     * @param $topic
     * @param $args
     * @param $kwargs
     * @return string
     */
    private function prepareBody($topic, $args, $kwargs)
    {
        $body = array();

        $body['topic'] = $topic;

        if (null !== $args) {
            $body['args'] = $args;
        }

        if (null !== $kwargs) {
            $body['kwargs'] = $kwargs;
        }

        return json_encode($body);
    }

    /**
     * @param $body
     * @return array
     */
    private function prepareSignature($body)
    {
        $query = array();

        $seq = mt_rand(0, pow(2, 12));
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $timestamp = $now->format("Y-m-d\TH:i:s.u\Z");

        $query['seq'] = $seq;
        $query['timestamp'] = $timestamp;

        if (null !== $this->key && null !== $this->secret) {

            $nonce = mt_rand(0, pow(2, 53));
            $signature = hash_hmac(
                'sha256',
                $this->key . $timestamp . $seq . $nonce . $body,
                $this->secret,
                true
            );

            $query['key'] = $this->key;
            $query['nonce'] = $nonce;
            $query['signature'] = strtr(base64_encode($signature), '+/', '-_');
        }

        return $query;
    }
}
