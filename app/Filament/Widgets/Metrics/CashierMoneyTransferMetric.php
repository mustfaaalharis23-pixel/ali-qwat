<?php

namespace App\Filament\Widgets\Metrics;

use App\Filament\Pages\FinancialDashboard;
use App\Invoices\TransferCashierMoney;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The cashier money transfer card: a list of the cashiers, each with the money on them, and a button next to it
 * that transfers all the money on the chosen cashier to the accountant's box after a read-only confirmation.
 * The money cannot be split: a transfer always moves all of it. Only a user with the transfer permission sees the card,
 * and the button also needs invoice.create. The rules of the transfer come from TransferCashierMoney.
 */
class CashierMoneyTransferMetric extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Metric cards are not added to the home dashboard on their own; each page lists the cards it shows.
     */
    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.cashier-money-transfer';

    /**
     * The card's width on the page grid, set from the page.
     *
     * @var int|string|array<string, int|null>
     */
    public int|string|array $columnSpan = 1;

    /**
     * The id of the cashier chosen in the browser, or empty when none is chosen. It is checked against the cashiers before it is used.
     */
    public ?string $cashier = null;

    public static function canView(): bool
    {
        return Gate::allows('transferCashierMoney', FinancialDashboard::class);
    }

    /**
     * Transfers all the money on the chosen cashier. It is disabled until a cashier with money is chosen,
     * and its confirmation shows the cashier, the description, and the amount, none of which can be changed.
     */
    public function transferAction(): Action
    {
        return Action::make('transfer')
            ->label(__('metrics.cashier_money_transfer.transfer'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (): bool => Gate::allows('create', Invoice::class))
            ->disabled(fn (): bool => $this->moneyOnSelectedCashier() <= 0)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalHeading(__('metrics.cashier_money_transfer.confirm_heading'))
            ->modalDescription(__('metrics.cashier_money_transfer.confirm_description'))
            ->modalSubmitActionLabel(__('metrics.cashier_money_transfer.transfer'))
            ->schema([
                TextEntry::make('cashier')
                    ->label(__('metrics.cashier_money_transfer.cashier'))
                    ->state(fn (): ?string => $this->selectedCashier()?->name),
                TextEntry::make('description')
                    ->label(__('metrics.cashier_money_transfer.description'))
                    ->state(fn (): ?string => (new TransferCashierMoney)->description()?->name)
                    ->placeholder('—'),
                TextEntry::make('amount')
                    ->label(__('metrics.cashier_money_transfer.amount'))
                    ->state(fn (): string => $this->formatMoney($this->moneyOnSelectedCashier())),
            ])
            ->action(function (Action $action): void {
                $cashier = $this->selectedCashier();

                if ($cashier === null) {
                    $action->cancel();
                }

                /** @var User $actor */
                $actor = auth()->user();
                $transfer = new TransferCashierMoney;
                $amount = $transfer->moneyOn($cashier);

                try {
                    $transfer->handle($actor, $cashier, $amount, today());
                } catch (ValidationException $exception) {
                    $this->notifyFailure(Arr::first(Arr::flatten($exception->errors())));
                    $action->cancel();
                } catch (AuthorizationException) {
                    $this->notifyFailure(null);
                    $action->cancel();
                }

                Notification::make()
                    ->title(__('metrics.cashier_money_transfer.transferred', [
                        'amount' => $this->formatMoney($amount),
                        'cashier' => $cashier->name,
                    ]))
                    ->success()
                    ->send();

                $this->dispatch(ValueMetric::MONEY_MOVED_EVENT);
            });
    }

    /**
     * @return array{label: string, cashiers: list<array{id: int, label: string}>, explanation: string, example: string}
     */
    protected function getViewData(): array
    {
        $transfer = new TransferCashierMoney;

        return [
            'label' => __('metrics.cards.cashier_money_transfer'),
            'cashiers' => User::query()
                ->where('is_cashier', true)
                ->orderBy('name')
                ->get()
                ->map(fn (User $cashier): array => [
                    'id' => $cashier->id,
                    'label' => "{$cashier->name} — {$this->formatMoney($transfer->moneyOn($cashier))}",
                ])
                ->all(),
            'explanation' => __('metrics.cards_info.cashier_money_transfer.explanation'),
            'example' => __('metrics.cards_info.cashier_money_transfer.example'),
        ];
    }

    /**
     * The chosen cashier, or null when none is chosen or the chosen value is not a cashier.
     */
    protected function selectedCashier(): ?User
    {
        if (blank($this->cashier)) {
            return null;
        }

        return User::query()->where('is_cashier', true)->find((int) $this->cashier);
    }

    /**
     * The money on the chosen cashier, or zero when none is chosen.
     */
    protected function moneyOnSelectedCashier(): float
    {
        $cashier = $this->selectedCashier();

        return $cashier === null ? 0.0 : (new TransferCashierMoney)->moneyOn($cashier);
    }

    protected function formatMoney(float $amount): string
    {
        $money = FormattedValue::of($amount, Currency::IraqiDinar);

        return "{$money->number} {$money->currency}";
    }

    protected function notifyFailure(?string $reason): void
    {
        Notification::make()
            ->title(__('metrics.cashier_money_transfer.failed'))
            ->body($reason)
            ->danger()
            ->send();
    }
}
