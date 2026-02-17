    <div class="login-container">
        <style>
            .login-form-overlay { background: rgba(0,0,0,0.45); padding:28px; border-radius:14px; backdrop-filter: blur(5px); }
            .separator-line { height:1px; background:rgba(255,255,255,0.08); margin:10px 0; border-radius:2px; }
            .connect-section { text-align:center; padding:15px 0 6px; }
            .connect-title { color:#fff; font-weight:600; margin-bottom:12px; }
            .connect-links { display:flex; gap:18px; justify-content:center; align-items:center; }
            .connect-icon { width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#fff; font-size:22px; box-shadow:0 8px 24px rgba(0,0,0,0.35); text-decoration:none; }
            .connect-icon.email { background:#e84b3c; }
            .connect-icon.whatsapp { background:#25d366; }
            .connect-icon i { font-size:22px; }
            /* Make remember label and forgot link white */
            .form-options .form-check-label { color: #fff !important; }
            .forgot-link { color: #fff !important; text-decoration: none; }
            .forgot-link:hover { color: #fff !important; text-decoration: underline; }
            @media (max-width:420px){ .connect-icon{width:52px;height:52px} }
        </style>
        <!-- Full-screen background image -->
        <div class="background-image"></div>

        <!-- Centered login form overlay -->
        <div class="login-form-overlay">
            <!-- User icon -->
            <div class="user-icon-container">
                <i class="bi bi-person-circle"></i>
            </div>

            <form wire:submit.prevent="login">
          

                <!-- Email field -->
                <div class="form-group">
                    <input type="email"
                        class="form-control {{ $errors->has('email') ? 'is-invalid shake' : '' }}"
                        wire:model="email"
                        placeholder="Enter Email"
                        required
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Password field -->
                <div class="form-group">
                    <input type="password"
                        class="form-control {{ $errors->has('password') ? 'is-invalid shake' : '' }}"
                        wire:model="password"
                        placeholder="Enter Password"
                        required
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Remember & Forgot options -->
                <div class="d-flex justify-content-between form-options">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-link">Forgot Password</a>
                </div>

                <!-- Login button -->
                <button type="submit" class="btn btn-primary login-btn">Login</button>

                <!-- Separator line -->
                <div class="separator-line"></div>

                <!-- Connect with us section -->
                <div class="connect-section">
                    <p class="connect-title">Connect with us</p>
                    <div class="connect-links">
                        <a href="mailto:contact@webxkey.com" class="connect-icon email" title="Email us">
                            <i class="bi bi-envelope-fill"></i>
                        </a>
                        <a href="https://api.whatsapp.com/send/?phone=94755299721&text=Hi%21+I%27m+interested+in+your+services.&type=phone_number&app_absent=0" 
                           target="_blank" 
                           class="connect-icon whatsapp" 
                           title="WhatsApp us" rel="noopener noreferrer">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
