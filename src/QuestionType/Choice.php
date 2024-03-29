<?php

namespace ExamParser\QuestionType;

use ExamParser\Constants\ParserSignal;
use ExamParser\Constants\QuestionElement;
use ExamParser\Constants\QuestionErrors;
use ExamParser\Dumper\DumperInterface;

class Choice extends AbstractQuestion implements QuestionInterface
{
    public function convert($questionLines)
    {
        $question = array(
            'stem' => '',
            'options' => array(),
            'difficulty' => 'normal',
            'score' => 2.0,
            'analysis' => '',
            'answers' => array(),
        );
        if (0 === strpos(trim($questionLines[0]), ParserSignal::CODE_UNCERTAIN_CHOICE_SIGNAL)) {
            $question['type'] = 'uncertain_choice';
            unset($questionLines[0]);
        }
        $preNode = QuestionElement::STEM;
        foreach ($questionLines as $line) {
            if ($this->matchOptions($question, $line, $preNode)) {
                continue;
            }
            if ($this->matchAnswers($question, $line, $preNode)) {
                continue;
            }
            if ($this->matchDifficulty($question, $line, $preNode)) {
                continue;
            }
            if ($this->matchScore($question, $line, $preNode)) {
                continue;
            }
            if ($this->matchAnalysis($question, $line, $preNode)) {
                continue;
            }
            if ($this->matchStem($question, $line, $preNode)) {
                continue;
            }
        }
        $this->fillOptions($question);
        $this->checkErrors($question);
        if (empty($question['type'])) {
            $question['type'] = 'single_choice';
        }

        return $question;
    }

    public function replaceSignals(&$content)
    {
        $content = preg_replace('/【不定项选择题】/', '<#不定项选择题#>', $content);
    }

    public function isMatch($questionLines)
    {
        $matches = preg_grep('/<#([A-J])#>/', $questionLines);
        return !empty($matches);
    }

    public function dump($item, DumperInterface $dumper)
    {
        if ('choice' != $item['type']) {
            return;
        }

        $dumper->buildStem($item['stem'], $item['num']);
        $dumper->buildOptions($item['options']);
        $dumper->buildAnswer($item['answer']);
        $this->dumpCommonModule($item, $dumper);
    }

    //补充空余选项
    protected function fillOptions(&$question)
    {
        $question['options'] = $this->sortOptions($question['options']);
    }

    protected function matchOptions(&$question, $line, &$preNode)
    {
        $node = 'default';
        if (false !== strpos($preNode, '_')) {
            list($node, $index) = explode('_', $preNode);
        }
        if (!$this->hasSignal($line) && QuestionElement::OPTIONS == $node) {
            $question['options'][$index] .= '<br/>'.$line;

            return true;
        }

        //处理A-J选项
        if (preg_match('/\s([A-J])(\.|、|。|\\s)/', $line)) {
            $optionStr = preg_replace('/\s([A-J])(\.|、|。|\\s)/', PHP_EOL.'<#$1#>', $line);
            $optionLines = explode(PHP_EOL, $optionStr);
            foreach ($optionLines as $line) {
                if (preg_match('/<#([A-J])#>/', $line, $matches)) {
                    $question['options'][ord($matches[1]) - 65] = preg_replace('/<#([A-J])#>/', '', $line);
                    $preNode = QuestionElement::OPTIONS.'_'.(ord($matches[1]) - 65);
                }
            }

            return true;
        } elseif (preg_match('/<#([A-J])#>/', $line, $matches)) {
            $question['options'][ord($matches[1]) - 65] = preg_replace('/<#([A-J])#>/', '', $line);
            $preNode = QuestionElement::OPTIONS.'_'.(ord($matches[1]) - 65);

            return true;
        }

        return false;
    }

    protected function matchAnswers(&$question, $line, &$preNode)
    {
        $answers = array();
        if (0 === strpos(trim($line), self::ANSWER_SIGNAL)) {
            preg_match_all('/[A-J]/', $line, $matches);
            if ($matches) {
                foreach ($matches[0] as $answer) {
                    $answerKey = ord($answer) - 65;
                    if (isset($question['options'][$answerKey])) {
                        $answers[] = $answerKey;
                    }
                }
            }
            $question['answers'] = $answers;
            if (empty($question['type'])) {
                if (count($answers) > 1) {
                    $question['type'] = 'choice';
                } else {
                    $question['type'] = 'single_choice';
                }
            }

            $preNode = QuestionElement::ANSWERS;

            return true;
        }

        return false;
    }

    protected function checkErrors(&$question)
    {
        //判断题干是否有错
        if (empty($question[QuestionElement::STEM])) {
            $question['errors'][QuestionElement::STEM] = $this->getError(QuestionElement::STEM, QuestionErrors::NO_STEM);
        }

        //判断选项是否有错
        foreach ($question[QuestionElement::OPTIONS] as $index => $option) {
            if (empty($option)) {
                $question['errors'][QuestionElement::OPTIONS.'_'.$index] = $this->getError(QuestionElement::OPTIONS, QuestionErrors::NO_OPTION, $index);
            }
        }

        //判断答案是否有错
        if (empty($question[QuestionElement::ANSWERS])) {
            $question['errors'][QuestionElement::ANSWERS] = $this->getError(QuestionElement::ANSWERS, QuestionErrors::NO_ANSWER);
        }
    }
}
