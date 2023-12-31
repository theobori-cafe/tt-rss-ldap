<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: opentelemetry/proto/trace/v1/trace.proto

namespace Opentelemetry\Proto\Trace\V1\Status;

use UnexpectedValueException;

/**
 * For the semantics of status codes see
 * https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/trace/api.md#set-status
 *
 * Protobuf type <code>opentelemetry.proto.trace.v1.Status.StatusCode</code>
 */
class StatusCode
{
    /**
     * The default status.
     *
     * Generated from protobuf enum <code>STATUS_CODE_UNSET = 0;</code>
     */
    const STATUS_CODE_UNSET = 0;
    /**
     * The Span has been validated by an Application developer or Operator to 
     * have completed successfully.
     *
     * Generated from protobuf enum <code>STATUS_CODE_OK = 1;</code>
     */
    const STATUS_CODE_OK = 1;
    /**
     * The Span contains an error.
     *
     * Generated from protobuf enum <code>STATUS_CODE_ERROR = 2;</code>
     */
    const STATUS_CODE_ERROR = 2;

    private static $valueToName = [
        self::STATUS_CODE_UNSET => 'STATUS_CODE_UNSET',
        self::STATUS_CODE_OK => 'STATUS_CODE_OK',
        self::STATUS_CODE_ERROR => 'STATUS_CODE_ERROR',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(StatusCode::class, \Opentelemetry\Proto\Trace\V1\Status_StatusCode::class);

