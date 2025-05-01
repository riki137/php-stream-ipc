<?php

declare(strict_types=1);

namespace PhpStreamIpc\Serialization;

use JsonException;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use ReflectionClass;
use Throwable;

final readonly class JsonMessageSerializer implements MessageSerializer
{
    /**
     * Convert a Message object (including all private/protected props, deeply)
     * into a JSON string.
     * @throws JsonException
     */
    public function serialize(Message $data): string
    {
        $array = $this->toArray($data);
        return json_encode($array, JSON_THROW_ON_ERROR);
    }

    /**
     * Rebuild an associative array from any object, capturing private &
     * protected props via reflection, including nested objects/arrays.
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
     * Recursively handle arrays that may contain objects or nested arrays.
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
     * Decode the JSON string back into a Message instance (or LogMessage on error).
     */
    public function deserialize(string $data): Message
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return new LogMessage($data, 'error');
        }

        if (!is_array($decoded) || !isset($decoded['__class'])) {
            return new LogMessage($data, 'error');
        }

        $class = $decoded['__class'];
        if (!\class_exists($class)) {
            return new LogMessage("Unknown class: {$class}", 'error');
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
            return new LogMessage($data, 'error');
        }

        if (!$inst instanceof Message) {
            return new LogMessage("Decoded object is not a Message: {$data}", 'error');
        }

        return $inst;
    }

    /**
     * Reverse of toArray/arrayToArray: rehydrate nested arrays or objects.
     */
    private function fromArray(mixed $data): mixed
    {
        if (is_array($data) && isset($data['__class'])) {
            $class = $data['__class'];
            if (!\class_exists($class)) {
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
