<?php

use App\Libraries\AppTheme;
use App\Libraries\Navigation;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Phase 11 design system / app shell helpers.
 *
 * @internal
 */
final class AppShellTest extends CIUnitTestCase
{
    public function testAccentColoursKeepReadableContrast(): void
    {
        $this->assertSame('#0f172a', AppTheme::readableOn('#ff7a1a'));
        $this->assertSame('#ffffff', AppTheme::readableOn('#1b2a3a'));
        $strong = AppTheme::shadeForContrast('#ff7a1a', '#ffffff', 4.5);
        $this->assertGreaterThanOrEqual(4.5, AppTheme::contrast($strong, '#ffffff'));
    }

    public function testInvalidSettingsFallBackToDefaults(): void
    {
        $css = AppTheme::cssVariables([
            'primary_color' => 'red;}body{display:none',
            'accent_color' => '#12',
            'font_family' => 'comic',
            'pos_font_size_px' => 99,
        ]);
        $this->assertStringContainsString('--lpg-brand:' . AppTheme::DEFAULT_PRIMARY . ';', $css);
        $this->assertStringContainsString('--lpg-accent:' . AppTheme::DEFAULT_ACCENT . ';', $css);
        $this->assertStringContainsString('--lpg-font-size:14px;', $css);
        $this->assertStringContainsString('"Inter Variable"', $css);
        $this->assertStringNotContainsString('display:none', $css);
    }

    public function testMenuShowsOnlyPermittedItemsAndOneActiveEntry(): void
    {
        $nav = Navigation::build(['EXPENSE_MANAGE' => true, 'CASH_MANAGE' => true], 'expenses/categories/5');

        $this->assertNull($nav['dashboard']);
        $this->assertSame(['cash'], array_keys($nav['groups']));
        $this->assertSame('expenses/categories', $nav['active']);
        $this->assertSame(['Cash & Expenses', 'Expense Categories'], $nav['trail']);
        $this->assertTrue($nav['groups']['cash']['open']);
    }

    public function testMenuAcceptsAnyOfSeveralPermissions(): void
    {
        $nav = Navigation::build(['ORDER_VIEW' => true, 'DASHBOARD_VIEW' => true], 'dashboard');

        $this->assertSame('dashboard', $nav['active']);
        $this->assertNotNull($nav['dashboard']);
        $this->assertSame(['customer-orders'], array_column($nav['groups']['ordering']['items'], 'url'));
        $this->assertSame([], $nav['trail']);
    }
}
