<header class="topbar">
    <div class="inner">

        <a href="index.php" class="logo">💄 COSMETIC</a>

        <div class="nav">
            <a href="index.php">首頁</a>
            <a href="products.php">產品</a>
            <a href="compare.php">比較</a>
            <a href="favorite.php">收藏</a>
            <a href="skin-match.php">膚色配對</a>
            <a href="../AI/story1.php">AI皮膚測試</a>
            <a href="../首頁/video.php">影片討論交流區</a>
        </div>

        <a href="login2.php" class="login-link">登入</a>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.addEventListener('submit', async function(event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) return;
                const action = form.getAttribute('action') || '';
                if (!action.includes('add_favorite.php') && !action.includes('remove_favorite.php')) return;

                event.preventDefault();
                const submitButton = form.querySelector('button[type=submit]');
                if (submitButton) submitButton.disabled = true;

                try {
                    const response = await fetch(action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const result = await response.json();
                    if (!result.success) {
                        console.error('收藏操作失敗', result);
                        return;
                    }

                    if (action.includes('add_favorite.php')) {
                        form.action = action.replace('add_favorite.php', 'remove_favorite.php');
                        if (submitButton) {
                            submitButton.innerHTML = submitButton.innerHTML.replace('🤍', '❤️');
                            submitButton.classList.add('active');
                        }
                    } else {
                        form.action = action.replace('remove_favorite.php', 'add_favorite.php');
                        if (submitButton) {
                            submitButton.innerHTML = submitButton.innerHTML.replace('❤️', '🤍');
                            submitButton.classList.remove('active');
                        }
                    }
                } catch (error) {
                    console.error('收藏請求錯誤', error);
                } finally {
                    if (submitButton) submitButton.disabled = false;
                }
            });
        });
    </script>
</header>