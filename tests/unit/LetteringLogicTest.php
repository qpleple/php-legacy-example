<?php
/**
 * Unit tests for lettering business logic
 *
 * Tests the core algorithms without database:
 * - Letter code generation
 * - Score calculation for suggestions
 * - Combination finding
 * - Validation rules
 */

require_once dirname(__DIR__) . '/unit_bootstrap.php';

class LetteringLogicTest extends PHPUnit\Framework\TestCase
{
    // ==================== Letter Code Generation ====================

    /**
     * Test letter code sequence generation
     * Format: AA, AB, AC... AZ, BA, BB... ZZ
     */
    public function testLetterCodeSequenceStart()
    {
        // First code should be AA
        $code = $this->generateNextLetterCode(null);
        $this->assertEquals('AA', $code);
    }

    public function testLetterCodeSequenceAA()
    {
        $code = $this->generateNextLetterCode('AA');
        $this->assertEquals('AB', $code);
    }

    public function testLetterCodeSequenceAZ()
    {
        $code = $this->generateNextLetterCode('AZ');
        $this->assertEquals('BA', $code);
    }

    public function testLetterCodeSequenceZZ()
    {
        // After ZZ, wrap to AA
        $code = $this->generateNextLetterCode('ZZ');
        $this->assertEquals('AA', $code);
    }

    public function testLetterCodeSequenceMiddle()
    {
        $code = $this->generateNextLetterCode('MN');
        $this->assertEquals('MO', $code);
    }

    public function testLetterCodePartialSuffix()
    {
        // Partial lettering should add 'P' suffix
        $code = $this->generateNextLetterCode('AB', true);
        $this->assertEquals('ACP', $code);
    }

    public function testLetterCodeAfterPartial()
    {
        // After a partial code (e.g., ABP), next should be AC
        $code = $this->generateNextLetterCode('ABP');
        $this->assertEquals('AC', $code);
    }

    public function testLetterCodeFullSequence()
    {
        // Test specific transitions in the sequence
        // Verify AY -> AZ transition
        $this->assertEquals('AZ', $this->generateNextLetterCode('AY'));

        // Verify AZ -> BA transition (column rollover)
        $this->assertEquals('BA', $this->generateNextLetterCode('AZ'));

        // Verify BY -> BZ transition
        $this->assertEquals('BZ', $this->generateNextLetterCode('BY'));

        // Verify ZY -> ZZ transition
        $this->assertEquals('ZZ', $this->generateNextLetterCode('ZY'));

        // Verify ZZ -> AA wrap-around
        $this->assertEquals('AA', $this->generateNextLetterCode('ZZ'));
    }

    // ==================== Scoring Algorithm ====================

    /**
     * Test scoring for exact amount match
     */
    public function testScoreExactAmountMatch()
    {
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 1);
        $credit = $this->makeLine(0, 1000.00, '2024-01-20', 1);

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Should get 40 points for exact amount match
        $this->assertGreaterThanOrEqual(40, $score);
    }

    public function testScoreAmountWithinTolerance()
    {
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 1);
        $credit = $this->makeLine(0, 999.97, '2024-01-20', 1);

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Should still get amount points (within 0.05 tolerance)
        $this->assertGreaterThanOrEqual(40, $score);
    }

    public function testScoreAmountOutsideTolerance()
    {
        // Use different third parties, different dates (>90 days), and piece numbers
        // with NO 4+ digit sequences to avoid reference matching
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 5, 'Inv', 'A-01');
        $credit = $this->makeLine(0, 999.00, '2024-06-15', 6, 'Pay', 'B-99');

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Amount diff is 1.00, which is > tolerance*10 (0.5), so 0 amount points
        // Different third parties: 0 points
        // No reference match (no 4+ digit numbers in labels/piece numbers): 0 points
        // Date > 90 days: 0 points
        // Total should be 0
        $this->assertEquals(0, $score);
    }

    public function testScoreSameThirdParty()
    {
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 5);
        $credit = $this->makeLine(0, 1000.00, '2024-01-20', 5);

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Should get 25 points for same third party (total 65+)
        $this->assertGreaterThanOrEqual(65, $score);
    }

    public function testScoreDifferentThirdParty()
    {
        // Use piece numbers with NO 4+ digit numbers to avoid reference match
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 5, 'Inv', 'A-01');
        $credit = $this->makeLine(0, 1000.00, '2024-06-15', 6, 'Pay', 'B-99');

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Amount: exact match = 40 points
        // Third party: different = 0 points
        // Reference: no 4+ digit numbers = 0 points
        // Date: >90 days = 0 points
        // Total: 40 points (no third party bonus)
        $this->assertEquals(40, $score);
    }

    public function testScoreDateProximityClose()
    {
        // Use piece numbers with NO 4+ digit numbers to avoid reference match
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 5, 'Inv', 'A-01');
        $credit = $this->makeLine(0, 1000.00, '2024-01-17', 6, 'Pay', 'B-99'); // 2 days apart

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Amount: exact match = 40 points
        // Third party: different = 0 points
        // Reference: no 4+ digit numbers = 0 points
        // Date: 2 days < 7 days = 15 points
        // Total: 55 points
        $this->assertEquals(55, $score);
    }

    public function testScoreDateProximityFar()
    {
        // Use piece numbers with NO 4+ digit numbers to avoid reference match
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 5, 'Inv', 'A-01');
        $credit = $this->makeLine(0, 1000.00, '2024-06-15', 6, 'Pay', 'B-99'); // 5 months apart

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Amount: exact match = 40 points
        // Third party: different = 0 points
        // Reference: no 4+ digit numbers = 0 points
        // Date: >90 days = 0 points
        // Total: 40 points (no date bonus included)
        $this->assertEquals(40, $score);
    }

    public function testScoreMaximum()
    {
        // Perfect match: same amount, same third party, same date
        $debit = $this->makeLine(1000.00, 0, '2024-01-15', 5);
        $credit = $this->makeLine(0, 1000.00, '2024-01-15', 5);

        $score = $this->calculateScore($debit, $credit, 0.05);

        // Should get close to maximum (40 + 25 + 15 = 80 without reference match)
        $this->assertGreaterThanOrEqual(80, $score);
    }

    // ==================== Combination Finding ====================

    /**
     * Test finding combinations that sum to target
     */
    public function testFindCombinationsExactMatch()
    {
        $lines = array(
            array('available_amount' => 300),
            array('available_amount' => 400),
            array('available_amount' => 300)
        );

        $combinations = $this->findCombinations($lines, 1000, 0.05, 5);

        // Should find the combination 300 + 400 + 300 = 1000
        $this->assertGreaterThanOrEqual(1, count($combinations));
    }

    public function testFindCombinationsWithTolerance()
    {
        $lines = array(
            array('available_amount' => 300),
            array('available_amount' => 400),
            array('available_amount' => 299.97) // Slightly off
        );

        $combinations = $this->findCombinations($lines, 1000, 0.05, 5);

        // Should find combination within tolerance
        $this->assertGreaterThanOrEqual(1, count($combinations));
    }

    public function testFindCombinationsNoMatch()
    {
        $lines = array(
            array('available_amount' => 100),
            array('available_amount' => 200),
            array('available_amount' => 300)
        );

        $combinations = $this->findCombinations($lines, 1000, 0.05, 5);

        // Should not find any valid combination (max sum is 600)
        $this->assertEquals(0, count($combinations));
    }

    public function testFindCombinationsPairMatch()
    {
        $lines = array(
            array('available_amount' => 500),
            array('available_amount' => 500),
            array('available_amount' => 100)
        );

        $combinations = $this->findCombinations($lines, 1000, 0.05, 5);

        // Should find 500 + 500 = 1000
        $this->assertGreaterThanOrEqual(1, count($combinations));
    }

    public function testFindCombinationsRespectMaxLines()
    {
        $lines = array(
            array('available_amount' => 200),
            array('available_amount' => 200),
            array('available_amount' => 200),
            array('available_amount' => 200),
            array('available_amount' => 200),
            array('available_amount' => 200) // 6 lines
        );

        // With max_lines = 3, should only find combinations of up to 3 items
        // Target 600 can be matched with 3 lines of 200 each
        $combinations = $this->findCombinations($lines, 600, 0.05, 3);

        // Should find at least one combination (3 x 200 = 600)
        $this->assertGreaterThan(0, count($combinations), 'Should find at least one combination');

        // All combinations should have at most 3 lines
        foreach ($combinations as $combo) {
            $this->assertLessThanOrEqual(3, count($combo));
        }
    }

    // ==================== Balance Validation ====================

    /**
     * Test balance validation with tolerance
     */
    public function testValidateBalanceExact()
    {
        $this->assertTrue($this->validateBalance(1000.00, 1000.00, 0.05));
    }

    public function testValidateBalanceWithinTolerance()
    {
        $this->assertTrue($this->validateBalance(1000.00, 999.97, 0.05));
        $this->assertTrue($this->validateBalance(1000.00, 1000.04, 0.05));
    }

    public function testValidateBalanceOutsideTolerance()
    {
        $this->assertFalse($this->validateBalance(1000.00, 999.90, 0.05));
        $this->assertFalse($this->validateBalance(1000.00, 1000.10, 0.05));
    }

    public function testValidateBalanceZero()
    {
        $this->assertTrue($this->validateBalance(0, 0, 0.05));
    }

    public function testValidateBalanceLargeAmounts()
    {
        $this->assertTrue($this->validateBalance(1000000.00, 1000000.00, 0.05));
        $this->assertTrue($this->validateBalance(1000000.00, 999999.97, 0.05));
    }

    public function testValidateBalanceDifferentTolerances()
    {
        // With larger tolerance
        $this->assertTrue($this->validateBalance(1000.00, 999.50, 0.50));

        // With smaller tolerance
        $this->assertFalse($this->validateBalance(1000.00, 999.97, 0.01));
    }

    // ==================== Account Code Validation ====================

    public function testIsLetterableAccountCustomer()
    {
        $this->assertTrue($this->isLetterableAccount('411000'));
        $this->assertTrue($this->isLetterableAccount('411DURAND'));
        $this->assertTrue($this->isLetterableAccount('411001'));
    }

    public function testIsLetterableAccountVendor()
    {
        $this->assertTrue($this->isLetterableAccount('401000'));
        $this->assertTrue($this->isLetterableAccount('401FOURN01'));
        $this->assertTrue($this->isLetterableAccount('401999'));
    }

    public function testIsLetterableAccountBank()
    {
        $this->assertFalse($this->isLetterableAccount('512000'));
        $this->assertFalse($this->isLetterableAccount('512100'));
    }

    public function testIsLetterableAccountOther()
    {
        $this->assertFalse($this->isLetterableAccount('601000')); // Expense
        $this->assertFalse($this->isLetterableAccount('701000')); // Revenue
        $this->assertFalse($this->isLetterableAccount('120000')); // Equity
        $this->assertFalse($this->isLetterableAccount('445000')); // VAT
    }

    // ==================== Third Party Consistency ====================

    public function testThirdPartyConsistencyAllSame()
    {
        $lines = array(
            array('third_party_id' => 5),
            array('third_party_id' => 5),
            array('third_party_id' => 5)
        );

        $this->assertTrue($this->checkThirdPartyConsistency($lines));
    }

    public function testThirdPartyConsistencyAllNull()
    {
        $lines = array(
            array('third_party_id' => null),
            array('third_party_id' => null)
        );

        $this->assertTrue($this->checkThirdPartyConsistency($lines));
    }

    public function testThirdPartyConsistencyMixed()
    {
        $lines = array(
            array('third_party_id' => 5),
            array('third_party_id' => 6)
        );

        $this->assertFalse($this->checkThirdPartyConsistency($lines));
    }

    public function testThirdPartyConsistencySomeNull()
    {
        $lines = array(
            array('third_party_id' => 5),
            array('third_party_id' => null)
        );

        // Null != 5, so should fail
        $this->assertFalse($this->checkThirdPartyConsistency($lines));
    }

    // ==================== Available Amount Calculation ====================

    public function testAvailableAmountFullyUnlettered()
    {
        $lineTotal = 1000.00;
        $letteredAmount = 0;

        $available = $this->calculateAvailableAmount($lineTotal, $letteredAmount);

        $this->assertEquals(1000.00, $available);
    }

    public function testAvailableAmountPartiallyLettered()
    {
        $lineTotal = 1000.00;
        $letteredAmount = 600.00;

        $available = $this->calculateAvailableAmount($lineTotal, $letteredAmount);

        $this->assertEquals(400.00, $available);
    }

    public function testAvailableAmountFullyLettered()
    {
        $lineTotal = 1000.00;
        $letteredAmount = 1000.00;

        $available = $this->calculateAvailableAmount($lineTotal, $letteredAmount);

        $this->assertEquals(0, $available);
    }

    public function testAvailableAmountWithRounding()
    {
        $lineTotal = 1000.00;
        $letteredAmount = 333.33;

        $available = $this->calculateAvailableAmount($lineTotal, $letteredAmount);

        $this->assertEquals(666.67, round($available, 2));
    }

    // ==================== Reference Matching ====================

    public function testReferenceMatchInvoiceNumber()
    {
        $debitLabel = 'Facture FA-2024-00123';
        $creditLabel = 'Reglement FA-2024-00123';

        $this->assertTrue($this->hasReferenceMatch($debitLabel, $creditLabel));
    }

    public function testReferenceMatchNoMatch()
    {
        $debitLabel = 'Facture FA-2024-00123';
        $creditLabel = 'Reglement virement';

        $this->assertFalse($this->hasReferenceMatch($debitLabel, $creditLabel));
    }

    public function testReferenceMatchPartialNumber()
    {
        $debitLabel = 'Facture 12345';
        $creditLabel = 'Paiement ref 12345';

        $this->assertTrue($this->hasReferenceMatch($debitLabel, $creditLabel));
    }

    // ==================== Helper Methods (mimic business logic) ====================

    /**
     * Generate next letter code (mirrors letter.php logic)
     */
    private function generateNextLetterCode($lastCode, $isPartial = false)
    {
        if ($lastCode === null || empty($lastCode)) {
            $code = 'AA';
        } else {
            // Remove 'P' suffix if present
            $lastCode = preg_replace('/P$/', '', $lastCode);

            if (strlen($lastCode) < 2) {
                $code = 'AA';
            } else {
                $first = ord($lastCode[0]);
                $second = ord($lastCode[1]);

                $second++;
                if ($second > ord('Z')) {
                    $second = ord('A');
                    $first++;
                }
                if ($first > ord('Z')) {
                    $first = ord('A');
                    $second = ord('A');
                }

                $code = chr($first) . chr($second);
            }
        }

        return $isPartial ? $code . 'P' : $code;
    }

    /**
     * Create a mock line for testing
     */
    private function makeLine($debit, $credit, $date, $thirdPartyId, $label = 'Test', $pieceNumber = 'VE2024-000001')
    {
        return array(
            'debit' => $debit,
            'credit' => $credit,
            'available_amount' => $debit + $credit,
            'entry_date' => $date,
            'third_party_id' => $thirdPartyId,
            'label' => $label,
            'piece_number' => $pieceNumber
        );
    }

    /**
     * Calculate score between two lines (mirrors ajax_suggest.php logic)
     */
    private function calculateScore($debitLine, $creditLine, $tolerance)
    {
        $score = 0;

        // Amount match (40 points)
        $debitAmount = floatval($debitLine['available_amount']);
        $creditAmount = floatval($creditLine['available_amount']);
        $amountDiff = abs($debitAmount - $creditAmount);

        if ($amountDiff <= $tolerance) {
            $score += 40;
        } elseif ($amountDiff <= $tolerance * 10) {
            $score += max(0, 40 - ($amountDiff / $tolerance) * 4);
        }

        // Same third party (25 points)
        if ($debitLine['third_party_id'] && $debitLine['third_party_id'] == $creditLine['third_party_id']) {
            $score += 25;
        }

        // Reference similarity (20 points) - simplified for unit test
        if ($this->hasReferenceMatch($debitLine['label'] . ' ' . $debitLine['piece_number'],
                                     $creditLine['label'] . ' ' . $creditLine['piece_number'])) {
            $score += 20;
        }

        // Date proximity (15 points)
        $debitDate = strtotime($debitLine['entry_date']);
        $creditDate = strtotime($creditLine['entry_date']);
        $dayDiff = abs($debitDate - $creditDate) / 86400;

        if ($dayDiff <= 7) {
            $score += 15;
        } elseif ($dayDiff <= 30) {
            $score += 10;
        } elseif ($dayDiff <= 90) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Find combinations of lines that sum to target
     */
    private function findCombinations($lines, $target, $tolerance, $maxLines, $start = 0, $current = array(), $currentSum = 0)
    {
        $results = array();

        // Check if current combination matches target
        if (count($current) >= 2 && abs($currentSum - $target) <= $tolerance) {
            $results[] = $current;
        }

        // Stop if we have too many lines
        if (count($current) >= $maxLines) {
            return $results;
        }

        // Try adding more lines
        for ($i = $start; $i < count($lines); $i++) {
            $amount = floatval($lines[$i]['available_amount']);
            $newSum = $currentSum + $amount;

            // Prune if we've exceeded target by too much
            if ($newSum > $target + $tolerance) {
                continue;
            }

            $newCurrent = $current;
            $newCurrent[] = $lines[$i];

            $subResults = $this->findCombinations($lines, $target, $tolerance, $maxLines, $i + 1, $newCurrent, $newSum);
            $results = array_merge($results, $subResults);

            // Limit results
            if (count($results) > 20) {
                break;
            }
        }

        return $results;
    }

    /**
     * Validate balance with tolerance
     */
    private function validateBalance($totalDebit, $totalCredit, $tolerance)
    {
        return abs($totalDebit - $totalCredit) <= $tolerance;
    }

    /**
     * Check if account code is letterable
     */
    private function isLetterableAccount($code)
    {
        return (substr($code, 0, 3) === '411' || substr($code, 0, 3) === '401');
    }

    /**
     * Check third party consistency across lines
     */
    private function checkThirdPartyConsistency($lines)
    {
        if (empty($lines)) {
            return true;
        }

        $firstThirdParty = $lines[0]['third_party_id'];

        foreach ($lines as $line) {
            if ($line['third_party_id'] != $firstThirdParty) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate available amount for a line
     */
    private function calculateAvailableAmount($lineTotal, $letteredAmount)
    {
        return $lineTotal - $letteredAmount;
    }

    /**
     * Check if two labels have a common reference (invoice number, etc.)
     */
    private function hasReferenceMatch($label1, $label2)
    {
        // Extract numbers (4+ digits) from both labels
        preg_match_all('/\d{4,}/', $label1, $numbers1);
        preg_match_all('/\d{4,}/', $label2, $numbers2);

        if (!empty($numbers1[0]) && !empty($numbers2[0])) {
            $common = array_intersect($numbers1[0], $numbers2[0]);
            if (!empty($common)) {
                return true;
            }
        }

        return false;
    }
}
