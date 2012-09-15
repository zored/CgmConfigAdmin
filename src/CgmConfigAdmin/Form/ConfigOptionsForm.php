<?php
/**
 * CgmConfigAdmin
 *
 * @link      http://github.com/cgmartin/CgmConfigAdmin for the canonical source repository
 * @copyright Copyright (c) 2012 Christopher Martin (http://cgmartin.com)
 * @license   New BSD License https://raw.github.com/cgmartin/CgmConfigAdmin/master/LICENSE
 */

namespace CgmConfigAdmin\Form;

use ZfcBase\Form\ProvidesEventsForm;
use CgmConfigAdmin\Model\ConfigGroup;
use CgmConfigAdmin\Model\ConfigOption;
use Zend\InputFilter\InputFilter;
use Zend\Form\Element\Csrf as CsrfElement;
use Zend\Form\Element\Button as ButtonElement;
use Zend\Validator\Explode as ExplodeValidator;
use Zend\Validator\InArray as InArrayValidator;
use Zend\Validator\ValidatorPluginManager;

class ConfigOptionsForm extends ProvidesEventsForm
{
    // Maps config option types to elements
    protected static $elementMappings = array(
        'radio'         => 'Zend\Form\Element\Radio',
        'select'        => 'Zend\Form\Element\Select',
        'multicheckbox' => 'Zend\Form\Element\MultiCheckbox',
        'text'          => 'Zend\Form\Element\Text',
        'number'        => 'Zend\Form\Element\Number',
    );

    /**
     * @var ValidatorPluginManager
     */
    protected $validatorPluginManager;

    /**
     * @param  array            $groups  Optional array of ConfigGroups
     * @param  null|int|string  $name    Optional name for the form
     * @param  array            $options Optional array of options
     */
    public function __construct(array $groups = array(), $name = null, array $options = array())
    {
        parent::__construct($name, $options);
        $this->filter = new InputFilter();

        $this->setAttribute('class', 'form-horizontal');

        $this->addConfigGroups($groups);

        $csrf = new CsrfElement('csrf');
        $csrf->setCsrfValidatorOptions(array('timeout' => null));
        $this->add($csrf);

        $resetBtn = new ButtonElement('reset');
        $resetBtn
            ->setLabel('Reset')
            ->setAttribute('type', 'submit')
            ->setValue('1');
        $this->add($resetBtn);

        $saveBtn = new ButtonElement('save');
        $saveBtn
            ->setLabel('Save')
            ->setAttribute('type', 'submit')
            ->setValue('1');
        $this->add($saveBtn);

        $previewBtn = new ButtonElement('preview');
        $previewBtn
            ->setLabel('Preview')
            ->setAttribute('type', 'submit')
            ->setValue('1');
        $this->add($previewBtn);
    }

    public function addConfigGroups(array $groups)
    {
        // Add fieldsets for all defined groups
        foreach ($groups as $groupId => $configGroup) {
            $this->add($this->createConfigGroupElementSpec($configGroup));
            $this->filter->add(
                $this->createConfigGroupInputFilterSpec($configGroup),
                $configGroup->getId()
            );
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getNumFieldsets()
    {
        return count($this->fieldsets);
    }

    /**
     * Create a form element spec from a ConfigGroup
     *
     * @return array
     */
    protected function createConfigGroupElementSpec(ConfigGroup $configGroup)
    {
        $elementSpec = array();

        $elementSpec['type'] = 'Zend\Form\Fieldset';
        $elementSpec['name'] = $configGroup->getId();
        $elementSpec['options']['label'] = $configGroup->getLabel();

        foreach ($configGroup->getConfigOptions() as $id => $configOption) {
            $elementSpec['elements'][]['spec'] = $this->createConfigOptionElementSpec($configOption);
        }

        return $elementSpec;
    }

    /**
     * Create a form element spec from a ConfigOption
     *
     * @return array
     */
    protected function createConfigOptionElementSpec(ConfigOption $configOption)
    {
        $configOption->prepare();
        $elementSpec = array();

        $type = $configOption->getInputType();
        $elementSpec['type'] = (array_key_exists($type, self::$elementMappings))
            ? self::$elementMappings[$type] : $type;

        $elementSpec['name']             = $configOption->getId();
        $elementSpec['options']['label'] = $configOption->getLabel();

        // Value
        if (null !== ($value = $configOption->getValue())) {
            $elementSpec['attributes']['value'] = $value;
        }

        // Value Options
        if (null !== ($valueOptions = $configOption->getValueOptions())) {
            $elementSpec['options']['value_options'] = $valueOptions;
        }

        return $elementSpec;
    }

    /**
     * Create an input filter spec from a ConfigGroup
     *
     * @param  ConfigGroup $configGroup
     * @return array
     */
    protected function createConfigGroupInputFilterSpec(ConfigGroup $configGroup)
    {
        $inputFilters = array(
            'type' => 'Zend\InputFilter\InputFilter',
        );

        foreach ($configGroup->getConfigOptions() as $id => $configOption) {
            $inputFilters[$id] = $this->createConfigOptionInputFilterSpec($configOption);
        }
        return $inputFilters;
    }

    /**
     * Create an input filter spec from a ConfigOption
     *
     * @param  ConfigOption $configOption
     * @return array
     */
    protected function createConfigOptionInputFilterSpec(ConfigOption $configOption)
    {
        $inputSpec = array();

        $type = $configOption->getInputType();

        $inputSpec['name']        = $configOption->getId();
        $inputSpec['required']    = false;
        $inputSpec['allow_empty'] = true;

        $validators = array();
        $filters    = array();
        switch ($type) {
            case 'radio':
            case 'select':
                $validators[] = $this->getInArrayValidator($configOption, false);
                break;
            case 'multicheckbox':
                $validators[] = $this->getExplodeValidator($configOption, true);
                break;
            //case 'text':
            case 'number':
                $filters[]    = array('name' => 'Zend\Filter\StringTrim');
                $validators[] = array(
                    'name'    => 'float',
                    'options' => array('locale' => 'en_US'),
                );
                break;
        }
        if (!empty($filters)) {
            $inputSpec['filters'] = $filters;
        }
        if (!empty($validators)) {
            $inputSpec['validators'] = $validators;
        }

        return $inputSpec;
    }

    /**
     * @param  ConfigOption $configOption
     * @param  bool         $includeEmpty
     * @return InArrayValidator
     */
    public function getInArrayValidator(ConfigOption $configOption, $includeEmpty = false)
    {
        $inarray = $this->getValidatorPluginManager()->create('inarray');
        $inarray->setHaystack($configOption->getValueOptionValues($includeEmpty));
        return $inarray;
    }

    /**
     * @param  ConfigOption $configOption
     * @param  bool         $includeEmpty
     * @return ExplodeValidator
     */
    public function getExplodeValidator(ConfigOption $configOption, $includeEmpty = false)
    {
        $explode = $this->getValidatorPluginManager()->create('explode');
        $explode
            ->setValidator($this->getInArrayValidator($configOption, $includeEmpty))
            ->setValueDelimiter(null); // skip explode if only one value

        return $explode;
    }

    /**
     * @return ValidatorPluginManager
     */
    public function getValidatorPluginManager()
    {
        if (!isset($this->validatorPluginManager)) {
            $this->setValidatorPluginManager(new ValidatorPluginManager());
        }
        return $this->validatorPluginManager;
    }

    /**
     * @param  ValidatorPluginManager $manager
     * @return ConfigOptionsForm
     */
    public function setValidatorPluginManager(ValidatorPluginManager $manager)
    {
        $this->validatorPluginManager = $manager;
        return $this;
    }

    /**
     * @static
     * @param array $mappings
     */
    public static function setElementMappings(array $mappings)
    {
        self::$elementMappings = $mappings;
    }

    /**
     * @static
     * @return array
     */
    public static function getElementMappings()
    {
        return self::$elementMappings;
    }

}