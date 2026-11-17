
<?php

function queueFlowEmailTemplate(string $type, array $data = []): string
{
    $name = htmlspecialchars($data['name'] ?? 'Student', ENT_QUOTES, 'UTF-8');
    $ticket = htmlspecialchars($data['ticket'] ?? '---', ENT_QUOTES, 'UTF-8');
    $service = htmlspecialchars($data['service'] ?? 'Finance Office', ENT_QUOTES, 'UTF-8');
    $counter = htmlspecialchars($data['counter'] ?? 'To be announced', ENT_QUOTES, 'UTF-8');

    $title = 'Welcome to QueueFlow!';
    $eyebrow = 'ACCOUNT CREATED';
    $heading = 'Your time matters.';
    $highlight = 'Your queue, simplified.';
    $message = 'Thank you for joining QueueFlow. You can now get your Finance Office queue number, monitor your status, and stay updated through one convenient online portal.';
    $footer = 'Thank you for choosing QueueFlow.';
    $statusColor = '#17834b';
    $statusBg = '#eaf6ef';
    $showTicket = false;
    $showCounter = false;

    if ($type === 'queued') {
        $title = 'Your queue ticket is confirmed';
        $eyebrow = 'WAITING IN QUEUE';
        $heading = 'You’re in the queue.';
        $highlight = 'Stay updated.';
        $message = 'Your queue ticket has been successfully registered. You can now monitor your queue status through the QueueFlow Student Portal. Please stay ready for your turn.';
        $footer = 'Please proceed to the assigned counter when your number is called.';
        $statusColor = '#a36b00';
        $statusBg = '#fff6df';
        $showTicket = true;

    } elseif ($type === 'serving') {
        $title = 'It’s your turn!';
        $eyebrow = 'NOW SERVING';
        $heading = 'Your turn is here!';
        $highlight = 'Please proceed to the counter.';
        $message = 'Your queue number is now being called. Please proceed to your assigned counter to begin your Finance Office transaction.';
        $footer = 'Please approach the counter promptly.';
        $statusColor = '#2563a6';
        $statusBg = '#eaf2fb';
        $showTicket = true;
        $showCounter = true;

    } elseif ($type === 'completed') {
        $title = 'Transaction completed';
        $eyebrow = 'TRANSACTION COMPLETED';
        $heading = 'One less thing to worry about.';
        $highlight = 'Your transaction is complete.';
        $message = 'Your Finance Office transaction has been successfully completed. Thank you for your patience and for using QueueFlow.';
        $footer = 'We hope QueueFlow made your experience easier.';
        $statusColor = '#17834b';
        $statusBg = '#eaf6ef';
        $showTicket = true;

    } elseif ($type === 'cancelled') {
        $title = 'Your queue ticket has been cancelled';
        $eyebrow = 'TICKET CANCELLED';
        $heading = 'Your ticket has been cancelled.';
        $highlight = 'You are no longer in the queue.';
        $message = 'Your request to cancel your Finance Office queue ticket has been successfully processed. This ticket will no longer be included in the active queue.';
        $footer = 'If you still need assistance, you may visit the QueueFlow Student Portal and request a new ticket.';
        $statusColor = '#c8102e';
        $statusBg = '#fff0f2';
        $showTicket = true;
    }

    $ticketSection = '';

    if ($showTicket) {
        $counterRow = '';

        if ($showCounter) {
            $counterRow = '
                <tr>
                    <td style="padding-top:16px;">
                        <div style="font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#737780;">
                            Assigned Counter
                        </div>
                        <div style="font-size:16px;font-weight:bold;color:#202124;margin-top:5px;">
                            ' . $counter . '
                        </div>
                    </td>
                </tr>';
        }

        $ticketSection = '
            <tr>
                <td style="padding:8px 32px 28px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0"
                        style="background:#fffafa;border:1px solid #f0dfe1;border-radius:16px;">
                        <tr>
                            <td style="padding:22px 24px 18px;">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="left" style="font-size:11px;font-weight:bold;letter-spacing:1.2px;color:#737780;">
                                            YOUR QUEUE OVERVIEW
                                        </td>
                                        <td align="right">
                                            <span style="
                                                display:inline-block;
                                                background:' . $statusBg . ';
                                                color:' . $statusColor . ';
                                                font-size:10px;
                                                font-weight:bold;
                                                letter-spacing:0.8px;
                                                padding:7px 10px;
                                                border-radius:7px;
                                            ">
                                                ' . $eyebrow . '
                                            </span>
                                        </td>
                                    </tr>
                                </table>

                                <div style="
                                    font-size:11px;
                                    font-weight:bold;
                                    letter-spacing:1.2px;
                                    color:#737780;
                                    margin-top:27px;
                                ">
                                    YOUR QUEUE NUMBER
                                </div>

                                <div style="
                                    font-size:56px;
                                    line-height:1.1;
                                    font-weight:800;
                                    letter-spacing:-2px;
                                    color:#c8102e;
                                    margin-top:8px;
                                ">
                                    ' . $ticket . '
                                </div>

                                <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                    style="margin-top:20px;border-top:1px dashed #ead8da;">
                                    <tr>
                                        <td style="padding-top:18px;">
                                            <div style="font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#737780;">
                                                Service
                                            </div>
                                            <div style="font-size:16px;font-weight:bold;color:#202124;margin-top:5px;">
                                                ' . $service . '
                                            </div>
                                        </td>
                                    </tr>
                                    ' . $counterRow . '
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';
    }

    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    </head>

    <body style="
        margin:0;
        padding:0;
        background-color:#fbf8f6;
        font-family:Arial,Helvetica,sans-serif;
        color:#202124;
    ">
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
            role="presentation"
            style="background-color:#fbf8f6;padding:32px 12px;">
            <tr>
                <td align="center">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0"
                        role="presentation"
                        style="
                            max-width:600px;
                            background:#ffffff;
                            border:1px solid #f0e9e6;
                            border-radius:16px;
                            overflow:hidden;
                        ">
                        <tr>
                            <td style="padding:23px 32px;border-bottom:1px solid #f0e9e6;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td valign="middle">
                                            <div style="
                                                font-size:25px;
                                                font-weight:800;
                                                letter-spacing:-1px;
                                                color:#202124;
                                            ">
                                                Queue<span style="color:#c8102e;">Flow</span>
                                            </div>
                                            <div style="
                                                font-size:12px;
                                                color:#737780;
                                                margin-top:4px;
                                            ">
                                                RMMC-MI · Finance Office
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:34px 32px 24px;background:#fffdfc;">
                                <table cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td width="38" valign="middle">
                                            <div style="width:38px;height:3px;background:#e6b944;"></div>
                                        </td>
                                        <td style="
                                            padding-left:12px;
                                            font-size:11px;
                                            font-weight:bold;
                                            letter-spacing:1px;
                                            color:#45434a;
                                        ">
                                            RAMON MAGSAYSAY MEMORIAL COLLEGES – MARBEL, INC.
                                        </td>
                                    </tr>
                                </table>

                                <div style="
                                    font-size:12px;
                                    font-weight:800;
                                    letter-spacing:2px;
                                    color:#c8102e;
                                    margin-top:30px;
                                ">
                                    FINANCE OFFICE QUEUE MANAGEMENT SYSTEM
                                </div>

                                <h1 style="
                                    font-size:36px;
                                    line-height:1.13;
                                    font-weight:800;
                                    letter-spacing:-1.5px;
                                    color:#252329;
                                    margin:17px 0 0;
                                ">
                                    ' . $heading . '
                                </h1>

                                <h2 style="
                                    font-size:30px;
                                    line-height:1.15;
                                    font-weight:800;
                                    letter-spacing:-1px;
                                    color:#c8102e;
                                    margin:5px 0 20px;
                                ">
                                    ' . $highlight . '
                                </h2>

                                <p style="
                                    font-size:15px;
                                    line-height:1.8;
                                    color:#66636b;
                                    margin:0;
                                ">
                                    Hello <strong style="color:#202124;">' . $name . '</strong>,
                                    <br><br>
                                    ' . $message . '
                                </p>
                            </td>
                        </tr>

                        ' . $ticketSection . '

                        <tr>
                            <td style="padding:0 32px 32px;">
                                <p style="
                                    font-size:13px;
                                    line-height:1.8;
                                    color:#66636b;
                                    margin:0;
                                ">
                                    ' . $footer . '
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="
                                background:#f7f7f9;
                                border-top:1px solid #e9e9ed;
                                padding:23px 32px;
                                text-align:center;
                            ">
                                <div style="
                                    font-size:17px;
                                    font-weight:800;
                                    letter-spacing:-0.5px;
                                    color:#202124;
                                ">
                                    Queue<span style="color:#c8102e;">Flow</span>
                                </div>

                                <div style="
                                    font-size:11px;
                                    line-height:1.8;
                                    color:#737780;
                                    margin-top:7px;
                                ">
                                    This is an automated message from QueueFlow.<br>
                                    RMMC-MI Finance Office
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div style="
                        max-width:560px;
                        font-size:10px;
                        line-height:1.6;
                        color:#a09ba0;
                        text-align:center;
                        margin-top:15px;
                    ">
                        © QueueFlow · RMMC-MI Finance Office
                    </div>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}
