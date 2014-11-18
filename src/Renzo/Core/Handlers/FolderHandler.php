<?php
/**
 * Copyright © 2014, REZO ZERO
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the REZO ZERO shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from the REZO ZERO SARL.
 *
 * @file FolderHandler.php
 * @copyright REZO ZERO 2014
 * @author Ambroise Maupate
 */
namespace RZ\Renzo\Core\Handlers;

use RZ\Renzo\Core\Kernel;
use RZ\Renzo\Core\Entities\Folder;
use RZ\Renzo\Core\Entities\FolderType;
use RZ\Renzo\Core\Entities\FolderTypeField;
use RZ\Renzo\Core\Entities\Translation;

/**
 * Handle operations with folders entities.
 */
class FolderHandler
{
    private $folder = null;

    /**
     * @return RZ\Renzo\Core\Entities\Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param RZ\Renzo\Core\Entities\Folder $folder
     *
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;

        return $this;
    }
    /**
     * Create a new folder handler with folder to handle.
     *
     * @param Folder $folder
     */
    public function __construct(Folder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * Remove only current folder children.
     *
     * @return $this
     */
    private function removeChildren()
    {
        foreach ($this->folder->getChildren() as $folder) {
            $folder->getHandler()->removeWithChildrenAndAssociations();
        }

        return $this;
    }

    /**
     * Remove current folder with its children recursively and
     * its associations.
     *
     * @return $this
     */
    public function removeWithChildrenAndAssociations()
    {
        $this->removeChildren();

        Kernel::getService('em')->remove($this->folder);

        /*
         * Final flush
         */
        Kernel::getService('em')->flush();

        return $this;
    }

    /**
     * Return every folder’s parents.
     *
     * @return array
     */
    public function getParents()
    {
        $parentsArray = array();
        $parent = $this->folder;

        do {
            $parent = $parent->getParent();
            if ($parent !== null) {
                $parentsArray[] = $parent;
            } else {
                break;
            }
        } while ($parent !== null);

        return array_reverse($parentsArray);
    }

    /**
     * Get folder full path using folder names.
     *
     * @return string
     */
    public function getFullPath()
    {
        $parents = $this->getParents();
        $path = array();

        foreach ($parents as $parent) {
            $path[] = $parent->getName();
        }

        $path[] = $this->folder->getName();

        return implode('/', $path);
    }

    /**
     * Clean position for current folder siblings.
     *
     * @return int Return the next position after the **last** folder
     */
    public function cleanPositions()
    {
        if ($this->folder->getParent() !== null) {
            return $this->folder->getParent()->getHandler()->cleanChildrenPositions();
        } else {
            return static::cleanRootFoldersPositions();
        }
    }

    /**
     * Reset current folder children positions.
     *
     * @return int Return the next position after the **last** folder
     */
    public function cleanChildrenPositions()
    {
        $children = $this->folder->getChildren();
        $i = 1;
        foreach ($children as $child) {
            $child->setPosition($i);
            $i++;
        }

        Kernel::getService('em')->flush();

        return $i;
    }

    /**
     * Reset every root folders positions.
     *
     * @return int Return the next position after the **last** folder
     */
    public static function cleanRootFoldersPositions()
    {
        $folders = Kernel::getService('em')
            ->getRepository('RZ\Renzo\Core\Entities\Folder')
            ->findBy(array('parent' => null), array('position'=>'ASC'));

        $i = 1;
        foreach ($folders as $child) {
            $child->setPosition($i);
            $i++;
        }

        Kernel::getService('em')->flush();

        return $i;
    }
}
