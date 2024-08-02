// webhooks basically are jo koi 3rd party hmary server ko send krti like hm zoom par hain tu koi meeting join krta tu woh hmary server par hmien hmary btaye hue url par ek request data send kr dy ga
// sb s pehly ap ko webhooks enable krna hoti and ap ko ek public accessile url dena hota .
// laravel verifycsrftoken.php ki file main ap n us route ko except ki array main rkhna ta k koi b request bhej sky 
// ab us route par koi b request bhej sakta tu jab b request ati tu woh agla server hmien header main ek signature deta jisko hm verify krty yeh check krny k lie k waqai zoom s aya and message tempered tu nhe 

 public function VerifyWebhook(Request $request)
    {
        try {

            // same if ($request->hasHeader('X-Hub-Signature')) 
            if (($signature = $request->headers->get('X-Hub-Signature')) == null) {
                throw new BadRequestHttpException('Header not set');   // signature tu h e nhe yeh kisi chabal na awein e request bhej di h 
            }



            $message = 'v0:' . $request->headers->get('x-zm-request-timestamp') . ':' . $request->getContent();    //  making a string of header timestamp plus request ki body
            $zoomwebhooksecrettoken = 'b6bUzjA0QkuQ0SZO8j8q8w';
            $hashed_message = hash_hmac('sha256', $message, $zoomwebhooksecrettoken); // this function generates the hash of a string it takes arggument like kis mehanism ya method main hash krna and woh string and uska salt .. this is same as firebase jwt token packgage
            Log::info("hashed message", $hashed_message);
            $hashed_message = 'v0=' . $hashed_message;
            $zoom_signature = $request->headers->get('x-zm-signature');   // yeh woh jo 3rd partt hmien send krti apna signature

            if (!hash_equals($hashed_message, $zoom_signature)) {   // ab 2no hash check kro 
                throw new UnauthorizedException('Could not verify request signature ' . $zoom_signature);
            }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return $e->getMessage();
        }
    }

    public function GetWebHooks(Request $request)
    {
        try {
            $this->VerifyWebhook($request);
            Log::info($request->all());
            return response()->json(200);
        } catch (Exception $e) {
            Log::info("error in webhook", $e->getMessage());
            return $e->getMessage();
        }
    }
