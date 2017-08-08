<?php

/*
 * This file is part of the OXID Console package.
 *
 * This file is based on Symfony\Component\Console\Input\ArgvInput.
 * Changes were made under copyright by Eligijus Vitkauskas for use with
 * special behaviour in OXID Console.
 *
 * (c) Eligijus Vitkauskas <eligijusvitkauskas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Argv Input, based on Symfony\Component\Console\Input\ArgvInput
 *
 * @author  Fabien Potencier <fabien@symfony.com>
 * @link    https://github.com/symfony/Console/blob/v2.6.0/Input/ArgvInput.php
 * @license https://github.com/symfony/Console/blob/v2.6.0/LICENSE
 */
class oxArgvInput implements oxIConsoleInput
{

    /**
     * @var array
     */
    protected $_aOptions = array();

    /**
     * @var string[]
     */
    protected $_aArguments = array();

    /**
     * @var oxConsoleOutput
     */
    protected $_oConsoleOutput;

    /**
     * Constructor
     *
     * @param array $aArgv
     *
     * @author Fabien Potencier <fabien@symfony.com>
     * @link   https://github.com/symfony/Console/blob/v2.6.0/Input/ArgvInput.php#L54
     */
    public function __construct(array $aArgv = null)
    {
        if (null === $aArgv) {
            $aArgv = $_SERVER['argv'];
        }

        // stripping application name
        array_shift($aArgv);

        $this->_parseTokens($aArgv);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstArgument()
    {
        return $this->getArgument(0);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->_aOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments()
    {
        return $this->_aArguments;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($mOption)
    {
        if (!is_array($mOption)) {
            $mOption = array($mOption);
        }

        foreach ($mOption as $sOptionName) {
            if (isset($this->_aOptions[$sOptionName])) {
                return $this->_aOptions[$sOptionName];
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($mOption)
    {
        if (!is_array($mOption)) {
            $mOption = array($mOption);
        }

        foreach ($mOption as $sOptionName) {
            if (array_key_exists($sOptionName, $this->_aOptions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getArgument($iOffset)
    {
        if (isset($this->_aArguments[$iOffset])) {
            return $this->_aArguments[$iOffset];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function prompt($sTitle = null)
    {
        $oOutput = $this->_getConsoleOutput();

        if (null !== $sTitle) {
            $oOutput->write($sTitle . ': ');
        }

        return trim(fgets(STDIN));
    }

    /**
     * Parse tokens to options and arguments
     *
     * @param string[] $aTokens
     */
    protected function _parseTokens(array $aTokens)
    {
        foreach ($aTokens as $sToken) {

            if ('--' === substr($sToken, 0, 2)) {
                $this->_parseLongOption($sToken);
            } else if ($sToken && '-' == $sToken[0]) {
                $this->_parseShortOption($sToken);
            } else {
                $this->_parseArgument($sToken);
            }
        }
    }

    /**
     * Parse long option from a token
     *
     * @param $sToken
     */
    protected function _parseLongOption($sToken)
    {
        $sOptionLine = substr($sToken, 2);
        if (!$sOptionLine) {
            return;
        }

        $aOption = explode('=', $sOptionLine, 2);
        if (!isset($aOption[1])) {
            $aOption[1] = true;
        }

        $this->_aOptions[$aOption[0]] = $aOption[1];
    }

    /**
     * Parse short option from a token
     *
     * @param $sToken
     */
    protected function _parseShortOption($sToken)
    {
        $sOptionLine = substr($sToken, 1);
        if (!$sOptionLine) {
            return;
        }

        foreach (str_split($sOptionLine) as $sOption) {
            $this->_aOptions[$sOption] = true;
        }
    }

    /**
     * Parse argument from a token
     *
     * @param $sToken
     */
    protected function _parseArgument($sToken)
    {
        if ($sToken) {
            $this->_aArguments[] = $sToken;
        }
    }

    /**
     * Get console output
     *
     * @return oxConsoleOutput
     */
    protected function _getConsoleOutput()
    {
        if (null === $this->_oConsoleOutput) {
            $this->_oConsoleOutput = oxNew('oxConsoleOutput');
        }

        return $this->_oConsoleOutput;
    }
}
