<?php
/**
 * File contaning the ezcTestConstraintSimilarImage class.
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @package UnitTest
 * @version //autogentag//
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

/**
 * Constraint class for image comparison.
 *
 * @package UnitTest
 * @version //autogentag//
 */
class ezcTestConstraintSimilarImage extends PHPUnit\Framework\Constraint\Constraint
{
    /**
     * Filename of the image to compare against.
     *
     * @var string
     */
    protected $filename;

    /**
     * Maximum delta between images.
     *
     * @var int
     */
    protected $delta;

    /**
     * Difference between images.
     *
     * @var int
     */
    protected $difference;

    /**
     * Constructor.
     *
     * @param string $filename Filename of the image to compare against.
     * @param int $delta Maximum delta between images.
     * @return ezcConstraintSimilarImage
     */
    public function __construct( $filename, $delta = 0 )
    {
        if ( is_string( $filename ) &&
             is_file( $filename ) &&
             is_readable( $filename ) )
        {
            $this->filename = $filename;
        }
        else
        {
            throw new ezcBaseFileNotFoundException( $filename );
        }

        $this->delta = (int) $delta;
    }

    /**
     * Evaluates the constraint for parameter $other. Returns TRUE if the
     * constraint is met, FALSE otherwise.
     *
     * @param mixed $other Filename of the image to compare.
     * @return bool
     * @abstract
     */
    public function evaluate( $other, $description = '', $returnResult = false ) : ?bool
    {
        if ( !is_string( $other ) ||
             !is_file( $other ) ||
             !is_readable( $other ) )
        {
            throw new ezcBaseFileNotFoundException( $other );
        }

        $descriptors = array(
            array( 'pipe', 'r' ),
            array( 'pipe', 'w' ),
            array( 'pipe', 'w' ),
        );
        $command = sprintf(
            'compare -metric MAE %s %s null:',
            escapeshellarg( $this->filename ),
            escapeshellarg( $other )
        );

        $imageProcess = proc_open( $command, $descriptors, $pipes );

        // Close STDIN pipe
        fclose( $pipes[0] );

        $errorString = '';
        // Read STDERR
        do
        {
            $errorString .= rtrim( fgets( $pipes[2], 1024 ) , "\n" );
        } while ( !feof( $pipes[2] ) );

        $resultString = '';
        // Read STDOUT
        do
        {
            $resultString .= rtrim( fgets( $pipes[1], 1024 ) , "\n" );
        }
        while ( !feof( $pipes[1] ) );

        // Wait for process to terminate and store return value
        $return = proc_close( $imageProcess );

        // Some versions output to STDERR
        if ( empty( $resultString ) && !empty( $errorString ) )
        {
            $resultString = $errorString;
        }

        // Different versuions of ImageMagick seem to output "dB" or not
        if ( preg_match( '/([\d.,e]+)(\s+dB)?/', $resultString, $match ) )
        {
            $this->difference = (int) $match[1];
            return ( $this->difference <= $this->delta );
        }

        return false;
    }

    /**
     * Creates the appropriate exception for the constraint which can be caught
     * by the unit test system. This can be called if a call to evaluate() fails.
     *
     * @param   mixed   $other The value passed to evaluate() which failed the
     *                         constraint check.
     * @param   string  $description A string with extra description of what was
     *                               going on while the evaluation failed.
     * @param   boolean $not Flag to indicate negation.
     * @throws  PHPUnit\Framework\ExpectationFailedException
     */
    public function fail( $other, $description, SebastianBergmann\Comparator\ComparisonFailure $comparisonFailure = NULL ) : void
    {
        $failureDescription = sprintf(
            'Failed asserting that image "%s" is similar to image "%s".',
            $other,
            $this->filename
        );

        if (!empty( $description ))
        {
            $failureDescription = $description . "\n" . $failureDescription;
        }

        throw new PHPUnit\Framework\ExpectationFailedException(
            $failureDescription,
            $comparisonFailure
        );
    }

    /**
     * Provide test text
     *
     * @return string Test description
     */
    public function toString() : string
    {
        return sprintf(
            'is similar to "%s"',
            $this->filename
        );
    }
}
?>
