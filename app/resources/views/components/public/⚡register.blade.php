<?php

use App\Exceptions\AlreadyRegisteredException;
use App\Exceptions\QuotaFullException;
use App\Exceptions\SessionNotOpenException;
use App\Http\Requests\RegisterDriverRequest;
use App\Models\SessionGroup;
use App\Models\WorkSession;
use App\Services\DriverRegistrationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Registrasi supir')] class extends Component
{
    public SessionGroup $group;

    public WorkSession $workSession;

    public string $name = '';

    public string $ktp = '';

    public bool $registered = false;

    public function mount(string $token): void
    {
        $this->group = SessionGroup::query()
            ->where('token', $token)
            ->with('workSession')
            ->firstOrFail();

        $this->workSession = $this->group->workSession;
    }

    public function submit(DriverRegistrationService $registration): void
    {
        if (! $this->workSession->isOpen() || $this->group->isFull()) {
            return;
        }

        $form = new RegisterDriverRequest;
        $validated = $this->validate($form->rules(), $form->messages());

        try {
            $registration->register($this->group, $validated['name'], $validated['ktp']);
            $this->registered = true;
        } catch (QuotaFullException|SessionNotOpenException) {
            // Tampilan dihitung ulang di bawah (Penuh / Ditutup).
        } catch (AlreadyRegisteredException) {
            $this->addError('ktp', 'KTP ini sudah terdaftar pada sesi ini.');
        }

        $this->group->refresh();
        $this->workSession->refresh();
    }
};
?>

<div class="w-full">
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05);">

        {{-- Header --}}
        <div style="text-align: center;">
            <div style="width: 56px; height: 56px; margin: 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #fff; box-shadow: 0 10px 20px -8px rgba(15, 23, 42, 0.4);">
                <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </div>
            <h1 style="margin: 16px 0 0; font-size: 22px; font-weight: 700; color: #0f172a;">Registrasi supir</h1>
            <p style="margin: 4px 0 0; font-size: 14px; color: #64748b;">
                {{ $workSession->route }} · {{ $workSession->date->translatedFormat('d M Y') }}
            </p>
            <!-- <span style="margin-top: 8px; display: inline-block; background: #f1f5f9; border-radius: 9999px; padding: 4px 12px; font-size: 12px; font-weight: 600; color: #475569;">
                Kelompok {{ $group->name }}
            </span> -->
        </div>

        @if ($registered)
            <div style="margin-top: 24px; border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 14px; padding: 20px;">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <svg style="width: 22px; height: 22px; flex-shrink: 0; color: #059669; margin-top: 2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p style="margin: 0; font-size: 14px; font-weight: 700; color: #065f46;">Pendaftaran berhasil</p>
                        <p style="margin: 4px 0 0; font-size: 13px; line-height: 1.5; color: #047857;">
                            Saat tiba di JMT, buka halaman check-in untuk konfirmasi kehadiran Anda.
                        </p>
                    </div>
                </div>

                <a href="{{ route('public.checkin') }}"
                   style="margin-top: 16px; display: flex; width: 100%; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; background: #0f172a; padding: 14px; font-size: 15px; font-weight: 600; color: #fff; text-decoration: none;">
                    Buka halaman Check-in
                    <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        @elseif ($workSession->hasClosed())
            <div style="margin-top: 24px; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 14px; padding: 20px; text-align: center;">
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #92400e;">Pendaftaran ditutup</p>
                <p style="margin: 6px 0 0; font-size: 13px; color: #a16207;">Sesi ini sudah melewati jam penutupan.</p>
            </div>
        @elseif (! $workSession->hasOpened())
            <div style="margin-top: 24px; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 14px; padding: 20px; text-align: center;">
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #92400e;">Belum dibuka</p>
                <p style="margin: 6px 0 0; font-size: 13px; color: #a16207;">Pendaftaran dibuka {{ $workSession->opens_at->translatedFormat('d M Y, H:i') }}.</p>
            </div>
        @elseif ($group->isFull())
            <div style="margin-top: 24px; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 14px; padding: 20px; text-align: center;">
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #92400e;">Kuota penuh</p>
                <p style="margin: 6px 0 0; font-size: 13px; color: #a16207;">Kuota sudah habis.</p>
                <!-- <p style="margin: 6px 0 0; font-size: 13px; color: #a16207;">Kuota kelompok {{ $group->name }} sudah habis.</p> -->
            </div>
        @else
            <div style="margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <span style="font-size: 13px; color: #64748b;">Sisa kuota:</span>
                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">{{ $group->remainingQuota() }}</span>
            </div>

            <form wire:submit="submit" style="margin-top: 20px; display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <label for="name" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Nama lengkap</label>
                    <input
                        id="name"
                        type="text"
                        wire:model="name"
                        autocomplete="name"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        placeholder="Contoh: Budi Santoso"
                        style="display: block; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; padding: 12px 14px; font-size: 15px; color: #0f172a; outline: none;"
                    >
                    @error('name') <p style="margin: 6px 0 0; font-size: 13px; color: #dc2626;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="ktp" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">No. KTP <span style="font-weight: 400; color: #64748b;">(16 digit)</span></label>
                    <input
                        id="ktp"
                        type="text"
                        inputmode="numeric"
                        maxlength="16"
                        pattern="[0-9]*"
                        autocomplete="off"
                        oninput="this.value = this.value.replace(/\D/g, '')"
                        wire:model="ktp"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        placeholder="1234567890123456"
                        style="display: block; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; padding: 12px 14px; font-family: 'Courier New', monospace; font-size: 15px; letter-spacing: 0.05em; color: #0f172a; outline: none;"
                    >
                    @error('ktp') <p style="margin: 6px 0 0; font-size: 13px; color: #dc2626;">{{ $message }}</p> @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    class="jmt-submit-btn"
                    style="display: flex; width: 100%; align-items: center; justify-content: center; border: 0; border-radius: 12px; background: #0f172a; padding: 14px; font-size: 15px; font-weight: 600; color: #fff; cursor: pointer;"
                >
                    <span class="jmt-submit-text" style="display: inline-flex; align-items: center; gap: 8px;">
                        Daftar sekarang
                    </span>
                    <span class="jmt-submit-loading" style="display: none; align-items: center; gap: 8px;">
                        <svg style="width: 18px; height: 18px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                        </svg>
                        Mendaftarkan...
                    </span>
                </button>
            </form>
        @endif

    </div>
</div>

<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .jmt-submit-btn[disabled] .jmt-submit-text { display: none !important; }
    .jmt-submit-btn[disabled] .jmt-submit-loading { display: inline-flex !important; }
</style>