<?php
// includes/footer.php
// Footer Component
?>
<footer class="footer">
    <div class="footer-text">© <?php echo date('Y'); ?> Salon Management System. All rights reserved.</div>
    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Contact Support</a>
    </div>
    <div class="developer-credits">
        Developed by Abc tech<br>
        <small>Mob: 91****** | Email: abctech@gmail.com | Instagram: @abctech</small>
    </div>
</footer>

<!-- Watermark Badge -->
<div class="watermark">
    <div style="font-weight: 600; margin-bottom: 3px; font-size: 13px;">⚡ Abc tech</div>
    <div style="font-size: 10px; color: #64748b;">Salon Management Pro</div>
</div>

<style>
    .footer {
        background: white;
        padding: 30px;
        text-align: center;
        border-top: 1px solid #e2e8f0;
        margin-top: 50px;
    }

    body.dark-mode .footer {
        background: #1e293b;
        border-top-color: #334155;
    }

    .footer-text {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 12px;
    }

    .footer-links {
        display: flex;
        justify-content: center;
        gap: 25px;
        font-size: 13px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .footer-links a {
        color: #6366f1;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-links a:hover {
        color: #4f46e5;
        text-decoration: underline;
    }

    .developer-credits {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
        font-weight: 600;
        color: #6366f1;
        font-size: 14px;
        line-height: 1.6;
    }

    body.dark-mode .developer-credits {
        border-top-color: #334155;
    }

    .developer-credits small {
        font-weight: 400;
        color: #64748b;
        font-size: 12px;
    }

    .watermark {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        padding: 12px 18px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        transition: all 0.3s ease;
    }

    body.dark-mode .watermark {
        background: #1e293b;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    .watermark:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.2);
    }

    @media (max-width: 768px) {
        .footer {
            padding: 20px 15px;
        }

        .footer-links {
            flex-direction: column;
            gap: 10px;
        }

        .watermark {
            bottom: 10px;
            right: 10px;
            padding: 8px 12px;
            font-size: 11px;
        }
    }
</style>