<?php

namespace OpenTok;

use OpenTok\Exception\BroadcastDomainException;
use OpenTok\Exception\BroadcastUnexpectedValueException;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Util\Client;
use OpenTok\Util\Validators;

/**
* Represents a broadcast of an OpenTok session.
*
* @property int $createdAt
* The timestamp when the broadcast was created, expressed in seconds since the Unix epoch.
*
* @property int $updatedAt
* The time the broadcast was started or stopped, expressed in seconds since the Unix epoch.
*
* @property string $id
* The unique ID for the broadcast.
*
* @property string $partnerId
* Your OpenTok API key.
*
* @property string $sessionId
* The OpenTok session ID.
*
* @property object $broadcastUrls
* Details on the HLS and RTMP broadcast streams. For an HLS stream, the URL is provided. See the
* <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/">OpenTok live streaming developer guide</a>
* for more information on how to use this URL. For each RTMP stream, the RTMP server URL and stream
* name are provided, along with the RTMP stream's status.
*
* @property boolean $isStopped
* Whether the broadcast is stopped (true) or in progress (false).
*
* @property string $streamMode
* Whether streams included in the broadcast are selected automatically (<code>StreamMode.AUTO</code>)
* or manually (<code>StreamMode.MANUAL</code>). When streams are selected automatically (<code>StreamMode.AUTO</code>),
* all streams in the session can be included in the broadcast. When streams are selected manually
* (<code>StreamMode.MANUAL</code>), you specify streams to be included based on calls to the
* <code>Broadcast.addStreamToBroadcast()</code> and <code>Broadcast.removeStreamFromBroadcast()</code> methods.
* With manual mode, you can specify whether a stream's audio, video, or both are included in the
* broadcast. In both automatic and manual modes, the broadcast composer includes streams based on
* <a href="https://tokbox.com/developer/guides/archive-broadcast-layout/#stream-prioritization-rules">stream
* prioritization rules</a>.
*/
class Broadcast
{
    // NOTE: after PHP 5.3.0 support is dropped, the class can implement JsonSerializable

    /** @ignore */
    private $data;
    /** @ignore */
    private $isStopped = false;
    /** @ignore */
    private $client;

    /** @ignore */
    public function __construct($broadcastData, $options = array())
    {
        // unpack optional arguments (merging with default values) into named variables
        $defaults = array(
            'apiKey' => null,
            'apiSecret' => null,
            'apiUrl' => 'https://api.opentok.com',
            'client' => null,
            'isStopped' => false,
            'streamMode' => StreamMode::AUTO
        );
        $options = array_merge($defaults, array_intersect_key($options, $defaults));
        list($apiKey, $apiSecret, $apiUrl, $client, $isStopped, $streamMode) = array_values($options);

        // validate params
        Validators::validateBroadcastData($broadcastData);
        Validators::validateClient($client);
        Validators::validateHasStreamMode($streamMode);

        $this->data = $broadcastData;

        $this->isStopped = $isStopped;

        $this->client = isset($client) ? $client : new Client();
        if (!$this->client->isConfigured()) {
            Validators::validateApiKey($apiKey);
            Validators::validateApiSecret($apiSecret);
            Validators::validateApiUrl($apiUrl);

            $this->client->configure($apiKey, $apiSecret, $apiUrl);
        }
    }

    /** @ignore */
    public function __get($name)
    {
        switch ($name) {
            case 'createdAt':
            case 'updatedAt':
            case 'id':
            case 'partnerId':
            case 'sessionId':
            case 'broadcastUrls':
            case 'status':
            case 'maxDuration':
            case 'resolution':
            case 'streamMode':
                return $this->data[$name];
            case 'hlsUrl':
                return $this->data['broadcastUrls']['hls'];
            case 'isStopped':
                return $this->isStopped;
            default:
                return null;
        }
    }
    /**
     * Stops the broadcast.
     */
    public function stop()
    {
        if ($this->isStopped) {
            throw new BroadcastDomainException(
                'You cannot stop a broadcast which is already stopped.'
            );
        }

        $broadcastData = $this->client->stopBroadcast($this->data['id']);

        try {
            Validators::validateBroadcastData($broadcastData);
        } catch (InvalidArgumentException $e) {
            throw new BroadcastUnexpectedValueException('The broadcast JSON returned after stopping was not valid', null, $e);
        }

        $this->data = $broadcastData;
        return $this;
    }

    // TODO: not yet implemented by the platform
    // public function getLayout()
    // {
    //     $layoutData = $this->client->getLayout($this->id, 'broadcast');
    //     return Layout::fromData($layoutData);
    // }

    /**
     * Updates the layout of the broadcast.
     * <p>
     * See <a href="https://tokbox.com/developer/guides/broadcast/live-streaming/#configuring-video-layout-for-opentok-live-streaming-broadcasts">Configuring
     * video layout for OpenTok live streaming broadcasts</a>.
     *
     * @param Layout $layout An object defining the layout type for the broadcast.
     */
    public function updateLayout($layout)
    {
        Validators::validateLayout($layout);

        // TODO: platform implementation did not meet API review spec
        // $layoutData = $this->client->updateLayout($this->id, $layout, 'broadcast');
        // return Layout::fromData($layoutData);

        $this->client->updateLayout($this->id, $layout, 'broadcast');
    }

    /**
     * Adds a stream to a currently running broadcast that was started with the
     * the <code>streamMode</code> set to <code>StreamMode.Manual</code>. You can call the method
     * repeatedly with the same stream ID, to toggle the stream's audio or video in the broadcast.
     * 
     * @param String $streamId The stream ID.
     * @param Boolean $hasAudio Whether the broadcast should include the stream's audio (true, the default)
     * or not (false).
     * @param Boolean $hasVideo Whether the broadcast should include the stream's video (true, the default)
     * or not (false).
     *
     * @return Boolean Returns true on success.
     */
    public function addStreamToBroadcast(string $streamId, bool $hasAudio, bool $hasVideo): bool
    {
        if ($this->streamMode === StreamMode::AUTO) {
            throw new InvalidArgumentException('Cannot add stream to a Broadcast in auto stream mode');
        }

        if ($hasAudio === false && $hasVideo === false) {
            throw new InvalidArgumentException('Both hasAudio and hasVideo cannot be false');
        }

        if ($this->client->addStreamToBroadcast(
            $this->data['id'],
            $streamId,
            $hasVideo,
            $hasVideo
        )) {
            return true;
        }

        return false;
    }

    /**
     * Removes a stream from a currently running broadcast that was started with the
     * the <code>streamMode</code> set to <code>StreamMode.Manual</code>.
     * 
     * @param String $streamId The stream ID.
     *
     * @return Boolean Returns true on success.
     */
    public function removeStreamFromBroadcast(string $streamId): bool
    {
        if ($this->streamMode === StreamMode::AUTO) {
            throw new InvalidArgumentException('Cannot remove stream from a Broadcast in auto stream mode');
        }

        if ($this->client->removeStreamFromBroadcast(
            $this->data['id'],
            $streamId
        )) {
            return true;
        }

        return false;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
