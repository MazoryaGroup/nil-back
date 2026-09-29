<div class="chat-base-container" style="direction: rtl; max-width: 1200px; margin: 0 auto;" x-data="chatComponent()">
    <div style="display: flex; flex-direction: column; height: 500px; background-color: #0A0A0F; border: 1px solid #374151; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">

        <div id="chat-window"
             style="flex: 1; overflow-y: auto; padding: 1rem; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded51.png'); background-size: 400px; background-blend-mode: overlay;"
             x-data="{ scroll: () => $el.scrollTop = $el.scrollHeight }"
             x-init="scroll()"
             @scroll-to-bottom.window="setTimeout(() => scroll(), 50)"
             wire:poll.5s>

            @foreach($this->chatMessages as $message)
                <div style="display: flex; justify-content: {{ $message->sender === 'admin' ? 'flex-start' : 'flex-end' }}; margin-bottom: 1rem;">
                    <div style="max-width: 80%; padding: 0.5rem 1rem; border-radius: 15px; font-size: 14px; position: relative;
                                {{ $message->sender === 'admin'
                                   ? 'background-color: #044533; color: white; border-top-right-radius: 0;'
                                   : 'background-color: #ff6b6b; color: #e9edef; border: 1px solid #374151; border-top-left-radius: 0;' }}">

                        <p style="margin: 0; line-height: 1.5; word-break: break-word;">{{ $message->message }}</p>

                        @if($message->file_path)
                            <div style="margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 8px;">
                                @php $fileUrl = \Illuminate\Support\Facades\Storage::disk('api_public')->url($message->file_path); @endphp
                                @if(Str::contains($message->file_path, ['jpg','png','jpeg','webp']))
                                    <img src="{{ $fileUrl }}" style="max-height: 200px; border-radius: 8px; cursor: pointer;" onclick="window.open('{{ $fileUrl }}')">
                                @else
                                    <audio controls style="width: 100%; height: 32px; filter: invert(1); opacity: 0.7;"><source src="{{ $fileUrl }}"></audio>
                                @endif
                            </div>
                        @endif

                        <span style="display: block; font-size: 9px; margin-top: 4px; opacity: 0.5; text-align: left; font-family: monospace;">
                            {{ $message->created_at->format('H:i') }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="padding: 1rem; background-color: #0A0A0F; border-top: 1px solid #374151;">
            <div style="display: flex; align-items: center; gap: 10px; background-color: #11111A; padding: 8px 12px; border-radius: 20px; border: 1px solid #374151;">

                <label style="cursor: pointer; color: #9ca3af; padding: 4px;">
                    <input type="file" wire:model="attachment" style="display: none;">
                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </label>

                <input type="text"
                       x-ref="messageInput"
                       wire:model.defer="messageText"
                       @keydown.enter="$wire.sendReply()"
                       placeholder="پیام..."
                       style="flex: 1; background: transparent; border: none; color: white; font-size: 14px; outline: none;">

                <button type="button"
                        wire:click="sendReply()"
                        style="background-color: #ff6b6b; color: white; padding: 8px; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg style="width: 20px; height: 20px; transform: rotate(0deg);" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
                @if ($this->attachment) {{-- تغییر به $this --}}
                <div style="margin: 10px; padding: 10px; background: #16161E; border: 1px dashed #374151; border-radius: 15px; display: flex; align-items: center; gap: 12px;">

                    @php
                        $mimeType = $this->attachment->getMimeType();
                    @endphp

                    @if (Str::contains($mimeType, 'image'))
                        <img src="{{ $this->attachment->temporaryUrl() }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                    @elseif (Str::contains($mimeType, ['audio', 'video', 'webm']))
                        <div style="flex: 1;">
                            <audio controls style="width: 100%; height: 30px; filter: invert(1);">
                                <source src="{{ $this->attachment->temporaryUrl() }}">
                            </audio>
                        </div>
                    @endif

                    <div style="flex: 1; font-size: 10px; color: #00a884;">فایل آماده ارسال...</div>
                    <button type="button" wire:click="$set('attachment', null)" style="background: #ef4444; color: white; border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer;">✕</button>
                </div>
                @endif

                <div wire:loading wire:target="attachment" style="position: absolute; bottom: 80px; left: 20px; background: #ff6b6b; color: white; padding: 5px 15px; border-radius: 10px; font-size: 11px; z-index: 1000; animation: pulse 1.5s infinite;">
                    ⏳ در حال آپلود فایل...
                </div>

                <style>
                    @keyframes pulse {
                        0% { opacity: 1; }
                        50% { opacity: 0.5; }
                        100% { opacity: 1; }
                    }
                </style>

                <button type="button"
                        @mousedown="startVoice()" @mouseup="stopVoice()"
                        @touchstart.prevent="startVoice()" @touchend.prevent="stopVoice()"
                        :style="isRecording ? 'background-color: #ef4444; color: white;' : 'background-color: #2a3942; color: #9ca3af;'"
                        style="width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0;">
                    <svg style="width: 20px; height: 20px;" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100-2h-3v-2.07z"></path></svg>
                </button>


                <div style="position: relative;" x-data="{ emojiOpen: false }">
                    <button type="button"
                            @click="emojiOpen = !emojiOpen"
                            style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 5px; display: flex; align-items: center;">
                        😊
                    </button>

                    <div x-show="emojiOpen"
                         @click.away="emojiOpen = false"
                         x-transition
                         style="position: absolute; bottom: 55px; left: 0; background-color: #11111A; border: 1px solid #374151; border-radius: 16px; padding: 12px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; z-index: 100; box-shadow: 0 10px 25px rgba(0,0,0,0.6); width: 180px;">

                        @foreach(['😊','😂','❤️','👍','✅','🔥','🙏','🌹','😢','😮','👏','🚀','✨','💯','🤔','👋'] as $emoji)
                            <button type="button"
                                    @click="addEmoji('{{$emoji}}'); emojiOpen = false"
                                    style="background: #1A1A24; border: 1px solid #2D2D39; border-radius: 10px; font-size: 1.3rem; cursor: pointer; padding: 6px;">
                                {{$emoji}}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function chatComponent() {
        return {
            isRecording: false,
            mediaRecorder: null,
            audioChunks: [],

            addEmoji(emoji) {
                // استفاده از @this برای اطمینان از ارتباط با کامپوننت
                const currentText = @this.get('messageText') || '';
            @this.set('messageText', currentText + emoji);

                // برگرداندن فوکوس به اینپوت
                this.$nextTick(() => {
                    this.$refs.messageInput.focus();
                });
            },

            async startVoice() {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.mediaRecorder = new MediaRecorder(stream);
                    this.audioChunks = [];

                    this.mediaRecorder.ondataavailable = e => {
                        if (e.data.size > 0) this.audioChunks.push(e.data);
                    };

                    this.mediaRecorder.onstop = () => {
                        const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                        const file = new File([blob], "voice_message.webm", { type: "audio/webm" });

                    @this.upload('attachment', file,
                        (uploadedName) => {
                            console.log('آپلود موفق:', uploadedName);
                        },
                        () => { alert('خطا در آپلود ویس'); }
                    );

                        stream.getTracks().forEach(track => track.stop());
                    };

                    this.mediaRecorder.start();
                    this.isRecording = true;
                } catch (e) {
                    console.error('Microphone Error:', e);
                    alert('دسترسی به میکروفون مسدود است. لطفاً تنظیمات مرورگر را چک کنید.');
                }
            },

            stopVoice() {
                if (this.mediaRecorder && this.isRecording) {
                    this.mediaRecorder.stop();
                    this.isRecording = false;
                }
            }
        }
    }
</script>
