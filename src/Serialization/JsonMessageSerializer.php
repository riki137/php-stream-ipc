<?php

declare(strict_types=1);

namespace StreamIpc\Serialization;

use JsonException;
use ReflectionClass;
use StreamIpc\Message\ErrorMessage;
use StreamIpc\Message\Message;
use Throwable;
use function class_exists;

/**
 * Serializes {@see Message} objects to and from JSON strings.
 * This serializer uses reflection to access all object properties (including private and protected)
 * and stores class information (`__class`) to enable deep reconstruction of the original object graph.
 * If deserialization encounters issues (e.g., invalid JSON, unknown class), it returns a {@see LogMessage}
 * with level 'error' containing the problematic data.
 */
final readonly class JsonMessageSerializer implements MessageSerializer
{
    /**
     * Serializes a {@see Message} object into a JSON string.
     * The resulting JSON includes a `__class` key with the original class name and all its properties,
     * including private and protected ones, to facilitate accurate deserialization.
     *
     * @param $data Message The message to serialize.
     * @throws JsonException If JSON encoding fails.
     */
    public function serialize(Message $data): string
    {
        $array = $this->toArray($data);
        return json_encode(
            $array,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Recursively converts an object into an associative array suitable for JSON serialization.
     * Captures all properties (public, protected, private) using reflection.
     * For nested objects, it includes a `__class` key with the object's class name.
     *
     * @param object $obj
     * @return array<string, mixed> Associative array representation of the object
     */
    private function toArray(object $obj): array
    {
        $ref = new ReflectionClass($obj);
        $result = ['__class' => $ref->getName()];

        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            $value = $prop->getValue($obj);

            if (is_object($value)) {
                $result[$prop->getName()] = $this->toArray($value);
            } elseif (is_array($value)) {
                $result[$prop->getName()] = $this->arrayToArray($value);
            } else {
                $result[$prop->getName()] = $value;
            }
        }

        return $result;
    }

    /**
     * Recursively processes an array, converting any nested objects to their array representations
     * using {@see self::toArray()} and handling nested arrays with {@see self::arrayToArray()}.
     *
     * @param array<int|string, mixed> $arr The array to process
     * @return array<int|string, mixed> The processed array with nested objects converted
     */
    private function arrayToArray(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            if (is_object($v)) {
                $out[$k] = $this->toArray($v);
            } elseif (is_array($v)) {
                $out[$k] = $this->arrayToArray($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Deserializes a JSON string back into a {@see Message} object.
     * It uses the `__class` metadata in the JSON to reconstruct the original object hierarchy.
     * If deserialization fails due to invalid JSON, an unknown class, or instantiation issues,
     * it returns a {@see LogMessage} with level 'error' and the original JSON data as its message.
     *
     * @param $data string The JSON string to deserialize.
     */
    public function deserialize(string $data): Message
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return new ErrorMessage('json_decode failed:' . $data, $e);
        }

        if (!is_array($decoded) || !isset($decoded['__class'])) {
            return new ErrorMessage('Invalid data to unserialize: ' . $data);
        }

        $class = $decoded['__class'];
        if (!class_exists($class)) {
            return new ErrorMessage("Unknown class: {$class}");
        }

        try {
            $ref = new ReflectionClass($class);
            $inst = $ref->newInstanceWithoutConstructor();
            unset($decoded['__class']);

            foreach ($decoded as $propName => $value) {
                if (!$ref->hasProperty($propName)) {
                    continue;
                }
                $prop = $ref->getProperty($propName);
                $prop->setAccessible(true);
                $prop->setValue($inst, $this->fromArray($value));
            }
        } catch (Throwable $e) {
            return new ErrorMessage('Failed to unserialize data:' . $data, $e);
        }

        if (!$inst instanceof Message) {
            return new ErrorMessage("Decoded object is not a Message: {$data}");
        }

        return $inst;
    }

    /**
     * Recursively reconstructs objects and arrays from their array representations (from JSON decoding).
     * If an array contains a `__class` key, it attempts to instantiate and populate that class.
     * Otherwise, it processes the array as a plain associative or indexed array.
     *
     * @param $data mixed Data (typically an array) to reconstruct from.
     * @return mixed The reconstructed object, array, or scalar value.
     */
    private function fromArray(mixed $data): mixed
    {
        if (is_array($data) && isset($data['__class'])) {
            $class = $data['__class'];
            if (!class_exists($class)) {
                return $data; // bail out if class missing
            }
            $ref = new ReflectionClass($class);
            $inst = $ref->newInstanceWithoutConstructor();
            unset($data['__class']);

            foreach ($data as $k => $v) {
                if (!$ref->hasProperty($k)) {
                    continue;
                }
                $prop = $ref->getProperty($k);
                $prop->setAccessible(true);
                $prop->setValue($inst, $this->fromArray($v));
            }

            return $inst;
        }

        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = $this->fromArray($v);
            }
            return $out;
        }

        return $data;
    }
}
