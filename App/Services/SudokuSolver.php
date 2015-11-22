<?php

namespace DawidDominiak\Sudoku\App\Services;


use DawidDominiak\Sudoku\App\Helpers\SolutionLeaf;
use DawidDominiak\Sudoku\App\Helpers\SolutionsTree;
use DawidDominiak\Sudoku\App\Values\Coordinates;
use DawidDominiak\Sudoku\App\Values\Plain;

class SudokuSolver
{
    /**
     * @var Plain
     */
    private $plain;

    /**
     * @var SolutionsTree
     */
    private $solutionsTree;

    /**
     * @var Plain
     */
    private $solution;

    /**
     * Sudoku constructor.
     * @param Plain $plain
     */
    public function __construct(Plain $plain)
    {
        $this->plain = $plain;
        $this->solutionsTree = new SolutionsTree();
    }

    /**
     * @return Plain
     */
    public function findSolution()
    {
        $plain = clone $this->plain;

        //TODO: check correct
        $this->tryFindPartialSolution($plain, $this->solutionsTree->getRoot());

        return $this->solution;
    }

    private function tryFindPartialSolution(Plain $plain, SolutionLeaf $previousLeaf)
    {
        $plain = $this->updateGridPossibleValues($plain, $previousLeaf);
        $gridCoordinates = $this->findCurrentlyBestLocation($plain);

        if(!$gridCoordinates)
        {
            $this->solution = $plain;

            return true;
        }

        $grid = $plain->getGrid($gridCoordinates);
        $possibleValues = $grid->getPossibleValues();

        foreach($possibleValues as $number)
        {
            $newLeaf = new SolutionLeaf($gridCoordinates, $number);
            $previousLeaf->addBranch($newLeaf);
            $grid->setValue($number);

            if($this->tryFindPartialSolution($plain, $newLeaf))
            {
                return true;
            }

            $previousLeaf->removeBranch($newLeaf);
            $grid->setValue(null);
        }

        return false;
    }

    /**
     * @param Plain|null $plain
     * @return \DawidDominiak\Sudoku\App\Values\Coordinates|null
     */
    private function findCurrentlyBestLocation(Plain $plain = null)
    {
        $countPossibleValues = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
            6 => [],
            7 => [],
            8 => [],
            9 => []
        ];

        if(!$plain)
        {
            $plain = $this->plain;
        }

        for($x = 0; $x < 9; $x++)
        {
            for($y = 0; $y < 9; $y++)
            {
                $grid = $plain->getGrid(new Coordinates($x, $y));

                if($grid->isEmpty())
                {
                    $count = count($grid->getPossibleValues());

                    if($count === 1)
                    {
                        return new Coordinates($x, $y);
                    }

                    array_push($countPossibleValues[$count], $grid);
                }

            }
        }

        foreach($countPossibleValues as $possibleValues)
        {
            if(count($possibleValues) > 0)
            {
                return $possibleValues[0];
            }
        }

        return null;
    }

    /**
     * @param Plain $plain
     * @param Coordinates $coordinates
     * @param number $number
     * @return bool
     */
    public function isSafe(Plain $plain, Coordinates $coordinates, $number)
    {
        $coordinatesArray = $coordinates->get();
        $x = $coordinatesArray['x'];
        $y = $coordinatesArray['y'];

        return !$this->usedInRow($plain, $x, $number) &&
            !$this->usedInColumn($plain, $y, $number) &&
            !$this->usedInBox($plain, new Coordinates($x - $x%3, $y - $y%3), $number);
    }

    private function updateGridPossibleValues(Plain $plain, SolutionLeaf $leaf)
    {
        $plain = clone $plain;

        if(get_class($leaf) === 'DawidDominiak\\Sudoku\\App\\Helpers\\SolutionRoot')
        {
            return $this->setFirstGridPossibleValues($plain);
        }

        return $this->fastUpdateGridPossibleValues($plain, $leaf);
    }

    private function setFirstGridPossibleValues(Plain $plain)
    {
        $plain = clone $plain;

        $valuesInRows = [];
        $valuesInColumns = [];
        $valuesInSquares = [];

        for($y = 0; $y < 9; $y++)
        {
            array_push($valuesInRows, $this->getValuesFromRow($plain, $y));
        }

        for($x = 0; $x < 9; $x++)
        {
            array_push($valuesInColumns, $this->getValuesFromColumn($plain, $x));
        }

        for($y = 0; $y < 9; $y += 3)
        {
            for($x = 0; $x < 9; $x += 3)
            {
                $hash = (new Coordinates($x, $y))->__toString();

                $valuesInSquares[$hash] = $this->getValuesFromSquare($plain, $x, $y);
            }
        }

        for($y = 0; $y < 9; $y++)
        {
            for($x = 0; $x < 9; $x++)
            {
                $coordinates = new Coordinates($x, $y);
                $grid = $plain
                    ->getGrid(
                        $coordinates
                    );

                $grid->removePossibleValues($valuesInRows[$y]);
                $grid->removePossibleValues($valuesInColumns[$x]);
                $grid->removePossibleValues(
                    $valuesInSquares[
                    (new Coordinates(
                        $x - $x%3,
                        $y - $y%3
                    ))
                        ->__toString()
                    ]
                );
            }
        }

        return $plain;
    }

    private function fastUpdateGridPossibleValues(Plain $plain, SolutionLeaf $leaf)
    {
        $plain = clone $plain;
        $coordinates = $leaf->getCoordinates();
        $nativeCoordinates = $coordinates->get();
        $value = $leaf->getValue();

        $currentX = $nativeCoordinates['x'];
        $currentY = $nativeCoordinates['y'];

        for($x = 0; $x < 9; $x++)
        {
            $grid = $plain->getGrid(
                new Coordinates($x, $currentY)
            );

            $grid->removePossibleValues([$value]);
        }

        for($y = 0; $y < 9; $y++)
        {
            $grid = $plain->getGrid(
                new Coordinates($currentX, $y)
            );

            $grid->removePossibleValues([$value]);
        }

        $squareX = $currentX - $currentX%3;
        $squareY = $currentY - $currentY%3;

        for($x = $squareX; $x < $squareX + 3; $x++)
        {
            for($y = $squareY; $y < $squareY + 3; $y++)
            {
                $grid = $plain->getGrid(
                    new Coordinates($x, $y)
                );

                $grid->removePossibleValues([$value]);
            }
        }

        return $plain;
    }

    private function getValuesFromRow(Plain $plain, $y)
    {
        $values = [];

        for($x = 0; $x < 9; $x++)
        {
            $this->pushValueEventually($values, $plain, new Coordinates($x, $y));
        }

        return $values;
    }

    private function getValuesFromColumn(Plain $plain, $x)
    {
        $values = [];

        for($y = 0; $y < 9; $y++)
        {
            $this->pushValueEventually($values, $plain, new Coordinates($x, $y));
        }

        return $values;
    }

    private function getValuesFromSquare(Plain $plain, $startX, $startY)
    {
        $values = [];

        for($y = $startY; $y < $startY + 3; $y++)
        {
            for($x = $startX; $x < $startX + 3; $x++)
            {
                $this->pushValueEventually($values, $plain, new Coordinates($x, $y));
            }
        }

        return $values;
    }

    private function pushValueEventually(&$valuesArray, Plain $plain, Coordinates $coordinates)
    {
        $currentGrid = $plain
            ->getGrid($coordinates)
        ;

        if(!$currentGrid->isEmpty())
        {
            array_push($valuesArray, $currentGrid->getValue());
        }
    }

    private function usedInRow(Plain $plain, $x, $number)
    {
        for($y = 0; $y < 9; $y++)
        {
            if($plain
                    ->getGrid(new Coordinates($x, $y))
                    ->getValue() === $number)
            {
                return true;
            }
        }
        return false;
    }

    private function usedInColumn(Plain $plain, $y, $number)
    {
        for($x = 0; $x < 9; $x++)
        {
            if($plain
                    ->getGrid(new Coordinates($x, $y))
                    ->getValue() == $number)
            {
                return true;
            }
        }
        return false;
    }

    private function usedInBox(Plain $plain, Coordinates $boxStartCoordinates, $number)
    {
        $coordinates = $boxStartCoordinates->get();

        for($x = 0; $x < 3; $x++)
        {
            for($y = 0; $y < 3; $y++)
            {
                if($plain
                        ->getGrid(
                            new Coordinates(
                                $x + $coordinates['x'],
                                $y + $coordinates['y']
                            )
                        )
                        ->getValue() === $number)
                {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return SolutionsTree
     */
    public function getSolutionsTree()
    {
        return $this->solutionsTree;
    }
}