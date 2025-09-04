<div class="social-auth-wrapper">
    <div class="social-auth-title">Войти с помощью социальных сервисов:</div>
    <div class="social-auth-container">
        <a href="{{ route('oauth.redirect', ['provider' => 'google']) }}" class="social-btn google-btn">
            <span class="social-icon">
            <svg viewBox="-0.5 0 48 48" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
              <title>Войти с помощью Google</title>
              <desc>Created with Sketch.</desc>
              <defs></defs>
              <g id="Icons" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <g id="Color-" transform="translate(-401.000000, -860.000000)">
                  <g id="Google" transform="translate(401.000000, 860.000000)">
                    <path d="M9.82727273,24 C9.82727273,22.4757333 10.0804318,21.0144 10.5322727,19.6437333 L2.62345455,13.6042667 C1.08206818,16.7338667 0.213636364,20.2602667 0.213636364,24 C0.213636364,27.7365333 1.081,31.2608 2.62025,34.3882667 L10.5247955,28.3370667 C10.0772273,26.9728 9.82727273,25.5168 9.82727273,24" id="Fill-1" fill="#FBBC05"></path>
                    <path d="M23.7136364,10.1333333 C27.025,10.1333333 30.0159091,11.3066667 32.3659091,13.2266667 L39.2022727,6.4 C35.0363636,2.77333333 29.6954545,0.533333333 23.7136364,0.533333333 C14.4268636,0.533333333 6.44540909,5.84426667 2.62345455,13.6042667 L10.5322727,19.6437333 C12.3545909,14.112 17.5491591,10.1333333 23.7136364,10.1333333" id="Fill-2" fill="#EB4335"></path>
                    <path d="M23.7136364,37.8666667 C17.5491591,37.8666667 12.3545909,33.888 10.5322727,28.3562667 L2.62345455,34.3946667 C6.44540909,42.1557333 14.4268636,47.4666667 23.7136364,47.4666667 C29.4455,47.4666667 34.9177955,45.4314667 39.0249545,41.6181333 L31.5177727,35.8144 C29.3995682,37.1488 26.7323182,37.8666667 23.7136364,37.8666667" id="Fill-3" fill="#34A853"></path>
                    <path d="M46.1454545,24 C46.1454545,22.6133333 45.9318182,21.12 45.6113636,19.7333333 L23.7136364,19.7333333 L23.7136364,28.8 L36.3181818,28.8 C35.6879545,31.8912 33.9724545,34.2677333 31.5177727,35.8144 L39.0249545,41.6181333 C43.3393409,37.6138667 46.1454545,31.6490667 46.1454545,24" id="Fill-4" fill="#4285F4"></path>
                  </g>
                </g>
              </g>
            </svg>
            </span>
        </a>

        <a href="{{ route('oauth.redirect', ['provider' => 'yandex']) }}" class="social-btn yandex-btn">
            <span class="social-icon">
                 <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve">
                     <title>Войти с помощью Яндекс</title>
                    <path style="fill:#D7143A;" d="M363.493,0h-72.744C217.05,0,142.684,54.422,142.684,176.006c0,62.978,26.691,112.027,75.619,139.922
                    l-89.552,162.091c-4.246,7.666-4.357,16.354-0.298,23.24c3.963,6.725,11.21,10.741,19.378,10.741h45.301
                    c10.291,0,18.315-4.974,22.163-13.688L299.26,334.08h6.128v157.451c0,11.096,9.363,20.469,20.446,20.469h39.574
                    c12.429,0,21.106-8.678,21.106-21.104V22.403C386.516,9.213,377.05,0,363.493,0z M305.388,261.126h-10.81
                    c-41.915,0-66.938-34.214-66.938-91.523c0-71.259,31.61-96.648,61.194-96.648h16.554V261.126z" />
                 </svg>
            </span>
        </a>

        <a href="{{ route('oauth.redirect', ['provider' => 'vkontakte']) }}" class="social-btn vk-btn">
            <span class="social-icon">
                <svg fill="#ffffff" viewBox="-2.5 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg">
                  <title>Войти с помощью VK</title>
                  <path d="M16.563 15.75c-0.5-0.188-0.5-0.906-0.531-1.406-0.125-1.781 0.5-4.5-0.25-5.656-0.531-0.688-3.094-0.625-4.656-0.531-0.438 0.063-0.969 0.156-1.344 0.344s-0.75 0.5-0.75 0.781c0 0.406 0.938 0.344 1.281 0.875 0.375 0.563 0.375 1.781 0.375 2.781 0 1.156-0.188 2.688-0.656 2.75-0.719 0.031-1.125-0.688-1.5-1.219-0.75-1.031-1.5-2.313-2.063-3.563-0.281-0.656-0.438-1.375-0.844-1.656-0.625-0.438-1.75-0.469-2.844-0.438-1 0.031-2.438-0.094-2.719 0.5-0.219 0.656 0.25 1.281 0.5 1.813 1.281 2.781 2.656 5.219 4.344 7.531 1.563 2.156 3.031 3.875 5.906 4.781 0.813 0.25 4.375 0.969 5.094 0 0.25-0.375 0.188-1.219 0.313-1.844s0.281-1.25 0.875-1.281c0.5-0.031 0.781 0.406 1.094 0.719 0.344 0.344 0.625 0.625 0.875 0.938 0.594 0.594 1.219 1.406 1.969 1.719 1.031 0.438 2.625 0.313 4.125 0.25 1.219-0.031 2.094-0.281 2.188-1 0.063-0.563-0.563-1.375-0.938-1.844-0.938-1.156-1.375-1.5-2.438-2.563-0.469-0.469-1.063-0.969-1.063-1.531-0.031-0.344 0.25-0.656 0.5-1 1.094-1.625 2.188-2.781 3.188-4.469 0.281-0.5 0.938-1.656 0.688-2.219-0.281-0.625-1.844-0.438-2.813-0.438-1.25 0-2.875-0.094-3.188 0.156-0.594 0.406-0.844 1.063-1.125 1.688-0.625 1.438-1.469 2.906-2.344 4-0.313 0.375-0.906 1.156-1.25 1.031z"></path>
                </svg>
            </span>
        </a>

        <a href="{{ route('oauth.redirect', ['provider' => 'mailru']) }}" class="social-btn mailru-btn">
            <span class="social-icon">
                <svg fill="#000000" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                    <title>Войти с помощью MailRu</title>
                    <path d="M15.448 7.021c2.443 0 4.745 1.083 6.432 2.776v0.005c0-0.813 0.547-1.428 1.303-1.428h0.192c1.193 0 1.432 1.125 1.432 1.48l0.005 12.635c-0.083 0.828 0.855 1.256 1.376 0.724 2.025-2.083 4.452-10.719-1.261-15.719-5.328-4.667-12.479-3.896-16.281-1.276-4.041 2.792-6.624 8.959-4.115 14.755 2.74 6.319 10.573 8.204 15.235 6.324 2.36-0.953 3.448 2.233 0.995 3.276-3.697 1.577-14 1.416-18.812-6.917-3.251-5.629-3.079-15.531 5.547-20.661 6.593-3.927 15.292-2.839 20.536 2.636 5.48 5.729 5.163 16.448-0.187 20.615-2.423 1.895-6.021 0.052-5.995-2.709l-0.027-0.9c-1.687 1.671-3.932 2.651-6.375 2.651-4.833 0-9.088-4.256-9.088-9.084 0-4.88 4.255-9.181 9.088-9.181zM21.527 15.855c-0.183-3.537-2.808-5.667-5.98-5.667h-0.12c-3.656 0-5.687 2.88-5.687 6.145 0 3.661 2.453 5.973 5.672 5.973 3.593 0 5.952-2.629 6.124-5.739z" />
                </svg>
            </span>
        </a>
    </div>
</div>

<style>
    .social-auth-wrapper {
        display: flex;
        flex-direction: column;
        gap: 16px;
        max-width: 100%;
        margin: 0 auto;
    }

    .social-auth-title {
        font-size: 16px;
        color: white;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .social-auth-container {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .social-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid transparent;
        min-height: 56px;
    }

    .social-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    }

    .social-icon {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 24px;
        height: 24px;
    }

    /* Google */
    .google-btn {
        background-color: #ffffff;
        border-color: #dadce0;
    }

    .google-btn:hover {
        background-color: #f8f9fa;
    }

    /* Яндекс */
    .yandex-btn {
        background-color: #ffcc00;
    }

    .yandex-btn:hover {
        background-color: #f2c200;
    }

    /* VK */
    .vk-btn {
        background-color: #0077ff;
    }

    .vk-btn:hover {
        background-color: #0066cc;
    }

    /* Mail.ru */
    .mailru-btn {
        background-color: #005ff9;
    }

    .mailru-btn:hover {
        background-color: #004ec7;
    }

    /* Мобильные устройства */
    @media (max-width: 768px) {
        .social-auth-container {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .social-btn {
            padding: 14px;
            min-height: 52px;
        }

        .social-icon {
            width: 20px;
            height: 20px;
        }
    }

    @media (max-width: 480px) {
        .social-auth-container {
            grid-template-columns: repeat(2, 1fr);
        }

        .social-btn {
            padding: 12px;
            min-height: 48px;
        }
    }
</style>
