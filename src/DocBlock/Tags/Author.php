<?php
/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2010-2015 Mike van Riel<mike@phpdoc.org>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace phpDocumentor\Reflection\DocBlock\Tags;

use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\DocBlock\Tag;

/**
 * Reflection class for an {@}author tag in a Docblock.
 */
final class Author extends Tag
{
    /** @var string register that this is the author tag. */
    protected $name = 'author';

    /** @var string The name of the author */
    private $authorName = '';

    /** @var string The email of the author */
    private $authorEmail = '';

    /**
     * Initializes this tag with the author name and e-mail.
     *
     * @param string $authorName
     * @param string $authorEmail
     */
    public function __construct($authorName, $authorEmail)
    {
        if (!is_string($authorName)) {
            throw new \InvalidArgumentException('The author tag does not have a valid name');
        }
        if (!is_string($authorEmail) || ($authorEmail && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL))) {
            throw new \InvalidArgumentException('The author tag does not have a valid e-mail address');
        }

        $this->authorName = $authorName;
        $this->authorEmail = $authorEmail;
    }

    /**
     * Gets the author's name.
     *
     * @return string The author's name.
     */
    public function getAuthorName()
    {
        return $this->authorName;
    }

    /**
     * Returns the author's email.
     *
     * @return string The author's email.
     */
    public function getEmail()
    {
        return $this->authorEmail;
    }

    /**
     * Returns this tag in string form.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->authorName . '<' . $this->authorEmail . '>';
    }

    /**
     * {@inheritdoc}
     */
    public static function create($content)
    {
        $splitTagContent = preg_match('/^([^\<]*)(?:\<([^\>]*)\>)?$/u', $content, $matches);
        if (!$splitTagContent) {
            return null;
        }

        $authorName = trim($matches[1]);
        $email = isset($matches[2]) ? trim($matches[2]) : '';

        return new static($authorName, $email);
    }
}
