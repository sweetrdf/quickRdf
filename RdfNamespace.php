<?php

/*
 * The MIT License
 *
 * Copyright 2021 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace dumbrdf;

use rdfInterface\NamedNode;

/**
 * Description of RdfNamespace
 *
 * @author zozlak
 */
class RdfNamespace implements \rdfInterface\RdfNamespace
{

    private $n          = 0;
    private $namespaces = [];

    public function add(string $uri, ?string $prefix = null): string
    {
        if (empty($prefix)) {
            $prefix = 'n' . $this->n;
            $this->n++;
        }
        $this->namespaces[$prefix] = $uri;
        return $prefix;
    }

    public function remove(string $prefix): void
    {
        unset($this->namespaces[$prefix]);
    }

    public function get(string $prefix): string
    {
        if (isset($this->namespaces[$prefix])) {
            return $this->namespaces[$prefix];
        }
        throw new RdfException('Unknown prefix');
    }

    public function getAll(): array
    {
        return $this->namespaces;
    }

    public function expand(string $shortIri): NamedNode
    {
        $pos   = strpos($shortIri, ':');
        $alias = substr($shortIri, 0, $pos);
        if (isset($this->namespaces[$alias])) {
            return $this->namespaces[$alias] . substr($shortIri, $pos + 1);
        }
        throw new RdfException('Unknown alias');
    }

    public function shorten(NamedNode $iri, bool $create): string
    {
        $iri = $iri->getValue();
        $n   = strlen($iri);
        $p   = max(strrpos($iri, '/'), strrpos($iri, '#'));
        if ($p + 1 >= $n) {
            throw new RdfException("Iri ending with # or / can't be shortened");
        }
        $iriFragment = substr($iri, 0, $p + 1);
        $prefix      = array_search($iriFragment, $this->namespaces);
        if ($prefix === false) {
            if ($create) {
                $prefix                    = "n" . $this->n;
                $this->n++;
                $this->namespaces[$prefix] = $iriFragment;
            } else {
                throw new RdfException("Iri doesn't match any registered prefix");
            }
        }
        return $prefix . ':' . substr($iri, $p + 1);
    }
}
