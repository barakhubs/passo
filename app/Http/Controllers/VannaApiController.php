<?php
namespace App\Http\Controllers;

use App\Services\VannaApiService;
use Illuminate\Http\Request;

class VannaApiController extends Controller
{
    protected $vannaService;

    public function __construct(VannaApiService $vannaService)
    {
        $this->vannaService = $vannaService;
    }

    public function chat(Request $request)
    {
        try {
            // Method 1: Get all responses at once
            $responses = $this->vannaService->chatStream(
                $request->input('message'),
                $request->input('user_email', 'markbrightbaraka@gmail.com')
            );

            return response()->json($responses);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function chatStreamCallback(Request $request)
    {
        try {
            // Method 2: Process responses as they come with callback
            $this->vannaService->chatStreamWithCallback(
                $request->input('message'),
                $request->input('user_email', 'markbrightbaraka@gmail.com'),
                function($type, $data) {
                    switch ($type) {
                        case 'text':
                            echo "\n-- Text Response --\n";
                            echo $data['text'] . "\n";
                            break;

                        case 'image':
                            echo "\n-- Image Response --\n";
                            echo "Image URL: " . $data['image_url'] . "\n";
                            if ($data['caption']) {
                                echo "Caption: " . $data['caption'] . "\n";
                            }
                            break;

                        case 'link':
                            echo "\n-- Link Response --\n";
                            echo "Title: " . $data['title'] . "\n";
                            echo "URL: " . $data['url'] . "\n";
                            if ($data['description']) {
                                echo "Description: " . $data['description'] . "\n";
                            }
                            break;

                        case 'end':
                            echo "\n-- End of Conversation --\n";
                            echo "Conversation ID: " . $data['conversation_id'] . "\n";
                            break;

                        default:
                            echo "\n-- Unknown Response Type --\n";
                            print_r($data);
                            break;
                    }

                    // Flush output for real-time display
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            );

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
