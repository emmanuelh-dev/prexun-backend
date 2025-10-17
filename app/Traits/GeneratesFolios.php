<?php

namespace App\Traits;

use App\Models\Card;
use App\Models\Campus;
use App\Models\Gasto;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait GeneratesFolios
{
  protected function shouldGenerateSpecificFolio($paymentMethod, $cardId = null)
  {
    if ($paymentMethod === 'cash') {
      return true;
    }

    if ($paymentMethod === 'transfer') {
      if (!$cardId) return true;

      $card = Card::find($cardId);
      return !($card && $card->sat);
    }

    if ($paymentMethod === 'card') {
      if (!$cardId) return false;

      $card = Card::find($cardId);
      return !($card && $card->sat);
    }

    return false;
  }

  protected function generateMonthlyFolio($campusId, $modelClass = Transaction::class, $dateColumn = 'payment_date', $date = null)
  {
    $targetDate = $date ? Carbon::parse($date) : now();
    $currentMonth = $targetDate->month;
    $currentYear = $targetDate->year;

    $maxFolio = $modelClass::where('campus_id', $campusId)
      ->whereNotNull('folio')
      ->where('folio', '>', 0)
      ->whereMonth($dateColumn, $currentMonth)
      ->whereYear($dateColumn, $currentYear)
      ->max(DB::raw("CAST(folio AS UNSIGNED)"));

    if (!$maxFolio) {
      return 1;
    }

    return $maxFolio + 1;
  }

  protected function generatePaymentMethodFolio($campusId, $paymentMethod, $cardId = null, $date = null)
  {
    $targetDate = $date ? Carbon::parse($date) : now();
    $currentMonth = $targetDate->month;
    $currentYear = $targetDate->year;

    if ($paymentMethod === 'transfer' && $cardId) {
      $card = Card::find($cardId);
      if ($card && $card->sat) {
        return null;
      }
    }

    if ($paymentMethod === 'card') {
      if (!$cardId) {
        return null;
      }

      $card = Card::find($cardId);
      if ($card && $card->sat) {
        return null;
      }
    }

    $folioConfig = $this->getFolioConfig($paymentMethod);
    if (!$folioConfig) {
      return null;
    }

    $maxFolio = Transaction::where('campus_id', $campusId)
      ->whereNotNull($folioConfig['column'])
      ->where($folioConfig['column'], '>', 0)
      ->whereMonth('payment_date', $currentMonth)
      ->whereYear('payment_date', $currentYear)
      ->max(DB::raw("CAST({$folioConfig['column']} AS UNSIGNED)"));

    $nextFolio = ($maxFolio ?? 0) + 1;

    return [
      'column' => $folioConfig['column'],
      'value' => $nextFolio,
      'formatted' => $folioConfig['prefix'] . str_pad($nextFolio, 4, '0', STR_PAD_LEFT)
    ];
  }

  protected function folioNew($campusId, $paymentMethod, $cardId = null, $payment_date = null)
  {
    Log::info([
      'campus_id' => $campusId,
      'payment_method' => $paymentMethod,
      'card_id' => $cardId,
      'payment_date' => $payment_date,
    ]);

    $date = $payment_date ? Carbon::parse($payment_date) : now();
    $mesAnio = $date->format('my');

    $campus = Campus::findOrFail($campusId);
    $letraCampus = strtoupper(substr($campus->name, 0, 1));

    $card = Card::find($cardId);

    $folioColumn = match ($paymentMethod) {
      'transfer' => $card && $card->sat ? 'I' : 'A',
      'cash' => 'E',
      'card' => 'I',
      default => null
    };

    if (!$folioColumn) {
      return null;
    }

    return $letraCampus . $folioColumn . '-' . $mesAnio . ' | ';
  }

  protected function getDisplayFolio($transaction)
  {
    $campus = Campus::find($transaction->campus_id);
    $letraCampus = $campus ? strtoupper(substr($campus->name, 0, 1)) : '';

    if ($transaction->payment_method === 'transfer' && $transaction->card_id) {
      $card = Card::find($transaction->card_id);
      if ($card && $card->sat) {
        return $letraCampus . ($transaction->folio_new ?? '');
      }
    }

    if ($transaction->payment_method === 'card') {
      if (!$transaction->card_id) {
        return $letraCampus . ($transaction->folio_new ?? '');
      }

      $card = Card::find($transaction->card_id);
      if ($card && $card->sat) {
        return $letraCampus . ($transaction->folio_new ?? '');
      }

      return $transaction->folio_card 
        ? $letraCampus . 'T' . str_pad($transaction->folio_card, 4, '0', STR_PAD_LEFT) 
        : $letraCampus . ($transaction->folio_new ?? '');
    }

    $folioConfig = $this->getFolioConfig($transaction->payment_method);
    if (!$folioConfig) {
      return $letraCampus . ($transaction->folio_new ?? '');
    }

    $folioColumn = str_replace('folio_', '', $folioConfig['column']);
    $folioValue = $transaction->{$folioConfig['column']};

    return $folioValue 
      ? $letraCampus . $folioConfig['prefix'] . str_pad($folioValue, 4, '0', STR_PAD_LEFT) 
      : $letraCampus . ($transaction->folio_new ?? '');
  }

  private function getFolioConfig($paymentMethod)
  {
    return match ($paymentMethod) {
      'transfer' => ['column' => 'folio_transfer', 'prefix' => 'A'],
      'cash' => ['column' => 'folio_cash', 'prefix' => 'E'],
      'card' => ['column' => 'folio_card', 'prefix' => 'T'],
      default => null
    };
  }

  protected function generateGastoFolioPrefix($campusId, $date = null)
  {
    $campus = Campus::findOrFail($campusId);
    $letraCampus = strtoupper(substr($campus->name, 0, 1));

    return $letraCampus . 'G-';
  }

  protected function generateGastoFolio($campusId)
  {
    $maxFolio = Gasto::where('campus_id', $campusId)
      ->whereNotNull('folio')
      ->where('folio', '>', 0)
      ->max(DB::raw("CAST(folio AS UNSIGNED)"));

    return ($maxFolio ?? 0) + 1;
  }

  protected function getGastoDisplayFolio($gasto)
  {
    $campus = Campus::find($gasto->campus_id);
    $letraCampus = $campus ? strtoupper(substr($campus->name, 0, 1)) : '';

    if ($gasto->folio && $gasto->folio_prefix) {
      return $gasto->folio_prefix . $gasto->folio;
    }

    return $letraCampus . 'G-' . ($gasto->folio ?? '');
  }
}
