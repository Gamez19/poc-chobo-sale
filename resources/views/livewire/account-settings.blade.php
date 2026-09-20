<div class="settings-stack">
    <header class="page-header">
        <div>
            <p class="eyebrow">Cuenta</p>
            <h1>Configuración</h1>
            <p class="page-description">Administrá tu contraseña y elegí cómo querés ver Choco Aventuras.</p>
        </div>
    </header>

    <div class="settings-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Actualizar contraseña</h2>
                    <p>Usá una contraseña larga y exclusiva para esta aplicación.</p>
                </div>
            </div>

            <form class="panel-body form-stack" wire:submit="updatePassword">
                <div class="form-group">
                    <label for="current-password">Contraseña actual</label>
                    <input id="current-password" class="input" type="password" wire:model="currentPassword" autocomplete="current-password">
                    @error('currentPassword') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="new-password">Nueva contraseña</label>
                    <input id="new-password" class="input" type="password" wire:model="password" autocomplete="new-password">
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="password-confirmation">Confirmar nueva contraseña</label>
                    <input id="password-confirmation" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password">
                    @error('password_confirmation') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Guardar contraseña</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Tema de la aplicación</h2>
                    <p>La elección se guarda en tu cuenta y se aplica en todos tus dispositivos.</p>
                </div>
            </div>

            <form class="panel-body form-stack" wire:submit="updateTheme">
                <div class="theme-options" role="radiogroup" aria-label="Tema de la aplicación">
                    @foreach ($themes as $value => $label)
                        <label class="theme-option {{ $theme === $value ? 'is-selected' : '' }}">
                            <input type="radio" wire:model="theme" value="{{ $value }}">
                            <span class="theme-preview theme-preview-{{ $value }}" aria-hidden="true"></span>
                            <span>
                                <strong>{{ $label }}</strong>
                                <small>
                                    @switch($value)
                                        @case('claro') Interfaz luminosa y neutra. @break
                                        @case('cacao') Tonos cálidos de la marca. @break
                                        @case('oscuro') Contraste alto para trabajar de noche. @break
                                    @endswitch
                                </small>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('theme') <span class="field-error">{{ $message }}</span> @enderror

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Guardar tema</button>
                </div>
            </form>
        </section>
    </div>
</div>
