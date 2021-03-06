<?php

namespace Proengsoft\JsValidation\Javascript;

use Proengsoft\JsValidation\Support\RuleListTrait;
use Proengsoft\JsValidation\Support\DelegatedValidator;
use Proengsoft\JsValidation\Support\UseDelegatedValidatorTrait;

class RuleParser
{
    use RuleListTrait, JavascriptRulesTrait, UseDelegatedValidatorTrait;

    /**
     * Rule used to validate remote requests.
     */
    const REMOTE_RULE = 'laravelValidationRemote';

    /**
     * Rule used to validate javascript fields.
     */
    const JAVASCRIPT_RULE = 'laravelValidation';

    /**
     * Token used to secure romte validations.
     *
     * @string|null $remoteToken
     */
    protected $remoteToken;

    /**
     * Conditional Validations.
     *
     * @var array
     */
    protected $conditional = [];

    /**
     * Create a new JsValidation instance.
     *
     * @param \Proengsoft\JsValidation\Support\DelegatedValidator $validator
     * @param string|null $remoteToken
     */
    public function __construct(DelegatedValidator $validator, $remoteToken = null)
    {
        $this->validator = $validator;
        $this->remoteToken = $remoteToken;
    }

    /**
     * Return parsed Javascript Rule.
     *
     * @param string $attribute
     * @param string $rule
     * @param $parameters
     * @param $rawRule
     *
     * @return array
     */
    public function getRule($attribute, $rule, $parameters, $rawRule)
    {
        $isConditional = $this->isConditionalRule($attribute, $rawRule);
        $isRemote = $this->isRemoteRule($rule);

        if ($isConditional || $isRemote) {
            list($attribute, $parameters) = $this->remoteRule($attribute, $isConditional);
            $jsRule = self::REMOTE_RULE;
        } else {
            list($jsRule, $attribute, $parameters) = $this->clientRule($attribute, $rule, $parameters);
        }

        $attribute = $this->getAttributeName($attribute);

        return [$attribute, $jsRule, $parameters];
    }

    /**
     * Gets rules from Validator instance.
     *
     * @return array
     */
    public function getValidatorRules()
    {
        return $this->validator->getRules();
    }

    /**
     * Add conditional rules.
     *
     * @param $attribute
     * @param array $rules
     */
    public function addConditionalRules($attribute, $rules = [])
    {
        foreach ((array) $attribute as $key) {
            $current = isset($this->conditional[$key]) ? $this->conditional[$key] : [];
            $merge = head($this->validator->explodeRules((array) $rules));
            $this->conditional[$key] = array_merge($current, $merge);
        }
    }

    /**
     * Determine if rule is passed with sometimes.
     *
     * @param $attribute
     * @param $rule
     * @return bool
     */
    protected function isConditionalRule($attribute, $rule)
    {
        return isset($this->conditional[$attribute]) &&
        in_array($rule, $this->conditional[$attribute]);
    }

    /**
     * Returns Javascript parameters for remote validated rules.
     *
     * @param string $attribute
     * @param $rule
     * @param $parameters
     *
     * @return array
     */
    protected function clientRule($attribute, $rule, $parameters)
    {
        $jsRule = self::JAVASCRIPT_RULE;
        $method = "rule{$rule}";

        if (method_exists($this, $method)) {
            list($attribute, $parameters) = $this->$method($attribute, $parameters);
        }

        return [$jsRule, $attribute, $parameters];
    }

    /**
     * Returns Javascript parameters for remote validated rules.
     *
     * @param string $attribute
     * @param bool $forceRemote
     *
     * @return array
     */
    protected function remoteRule($attribute, $forceRemote)
    {
        $attrHtmlName = $this->getAttributeName($attribute);
        $params = [
            $attrHtmlName,
            $this->remoteToken,
            $forceRemote,
        ];

        return [$attribute, $params];
    }

    /**
     * Handles multidimensional attribute names.
     *
     * @param $attribute
     *
     * @return string
     */
    protected function getAttributeName($attribute)
    {
        $attributeArray = explode('.', $attribute);
        if (count($attributeArray) > 1) {
            return $attributeArray[0].'['.implode('][', array_slice($attributeArray, 1)).']';
        }

        return $attribute;
    }
}
