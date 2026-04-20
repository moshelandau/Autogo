<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Seeder;

class XdeskProDealsSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create salespeople
        $autoGoOffice = User::firstOrCreate(
            ['email' => 'autogooffice@autogoco.com'],
            ['name' => 'AutoGo Office', 'password' => bcrypt('changeme')]
        );

        $hershyGantz = User::firstOrCreate(
            ['email' => 'hershy.gantz@autogoco.com'],
            ['name' => 'Hershy Gantz', 'password' => bcrypt('changeme')]
        );

        $deals = [
            // Page 1
            ['#284', 'Shulem Frankel', null, 'Lead', 'AutoGo', '04/16/2026'],
            ['#283', 'Isumer t Shvarts', null, 'Quote', 'AutoGo', '04/16/2026'],
            ['#282', 'Burich Yida Spitzer', null, 'Finalize', 'AutoGo', '04/14/2026'],
            ['#281', 'Avrohom Spilman', null, 'Lead', 'AutoGo', '04/13/2026'],
            ['#280', 'Mordcha Friedman', null, 'Pending', 'AutoGo', '04/13/2026'],
            ['#279', 'Yossi Rosenberg', '2026 Honda Odyssey 5D Wagon EX-L', 'Outstanding', 'AutoGo', '04/13/2026'],
            ['#278', 'Simcha Appel', null, 'Quote', 'AutoGo', '04/13/2026'],
            ['#277', 'Moses Ekstein', null, 'Pending', 'AutoGo', '04/13/2026'],
            ['#276', 'Shloma Landau', '2026 Honda Odyssey 5D Wagon EX-L', 'Complete', 'AutoGo', '04/10/2026'],
            ['#273', 'David Berger', '2025 Lincoln Aviator 5D Wagon Reserve AWD', 'Outstanding', 'AutoGo', '03/25/2026'],
            ['#272', 'Shmiel Hilman Davidowitz', null, 'Lead', 'AutoGo', '03/25/2026'],
            ['#271', 'Moshe Amrom Lieberman', '2027 Kia Telluride 4D Wagon S AWD', 'Complete', 'AutoGo', '03/24/2026'],
            ['#270', 'Alexander Weiss', '2026 Honda Odyssey 5D Wagon EX-L', 'Outstanding', 'AutoGo', '03/24/2026'],
            ['#269', 'Moshe Arye Gantz', '2026 Kia Carnival 4D Wagon LXS', 'Complete', 'AutoGo', '03/23/2026'],
            ['#268', 'Bruche Misles', null, 'Lead', 'AutoGo', '03/23/2026'],
            ['#267', 'Ely Halperin', '2026 Honda Odyssey 5D Wagon Elite', 'Quote', 'AutoGo', '03/19/2026'],
            ['#266', 'Hershy Weiss', '2026 Honda CR-V 5D Wagon EX AWD', 'Application', 'AutoGo', '03/17/2026'],
            ['#265', 'Duddy Koppel', '2026 Hyundai Tucson 5D Wagon SE AWD', 'Complete', 'AutoGo', '03/16/2026'],
            ['#264', 'Yisroel simcha Landau', '2026 Honda Odyssey 5D Wagon EX-L', 'Outstanding', 'AutoGo', '03/16/2026'],
            ['#263', 'Moshe hersh Rosenfeld', '2026 Honda CR-V Hybrid Sport Touring AWD', 'Outstanding', 'AutoGo', '03/12/2026'],
            ['#261', 'Luzer Berger', null, 'Lead', 'AutoGo', '03/10/2026'],
            ['#260', 'Luzer Berger', null, 'Application', 'AutoGo', '03/06/2026'],
            ['#259', 'Joel Hirsch', '2026 Honda Odyssey 5D Wagon Touring', 'Outstanding', 'AutoGo', '03/02/2026'],
            ['#257', 'Gesheft Supermarket', null, 'Lead', 'Hershy Gantz', '02/26/2026'],
            ['#256', 'Juan Castillo', null, 'Lead', 'Hershy Gantz', '02/26/2026'],
            // Page 2
            ['#255', 'Refoel Wertheimer', null, 'Quote', 'AutoGo', '02/24/2026'],
            ['#254', 'Avrumy Drezdner', null, 'Quote', 'AutoGo', '02/24/2026'],
            ['#250', 'Shulem Eisenberg', '2026 Kia Carnival Hybrid 4D Wagon LXS HEV', 'Complete', 'AutoGo', '02/17/2026'],
            ['#249', 'Shmiel weinberger', '2026 Honda Odyssey 5D Wagon Touring', 'Outstanding', 'AutoGo', '02/12/2026'],
            ['#248', 'Sruly Weiser', null, 'Lead', 'AutoGo', '02/11/2026'],
            ['#247', 'Gurci Pamukci', '2026 Kia Sportage 4D Wagon SX Prestige AWD', 'Outstanding', 'AutoGo', '02/06/2026'],
            ['#246', 'Yitzchok Landau', '2026 Infiniti QX60 4D Wagon Luxe AWD', 'Outstanding', 'AutoGo', '02/04/2026'],
            ['#244', 'Meshilem Friedman', '2026 Chevrolet Trailblazer 4D Wagon LT FWD', 'Complete', 'AutoGo', '01/28/2026'],
            ['#243', 'Fishel Goldberger', '2026 Hyundai Palisade 4D Wagon SEL 7 Seat AWD', 'Outstanding', 'AutoGo', '01/28/2026'],
            ['#242', 'David Farkas', '2025 Nissan Pathfinder 4D Wagon SL 4WD', 'Outstanding', 'AutoGo', '01/26/2026'],
            ['#241', 'Berish Landau', '2026 Hyundai Palisade 4D Wagon SEL Convenience AWD', 'Complete', 'AutoGo', '01/26/2026'],
            ['#240', 'Chaim Shlome Ackerman', '2026 Hyundai Santa Fe 4D Wagon SE AWD', 'Complete', 'AutoGo', '01/26/2026'],
            ['#239', 'David Halpert', '2026 Kia EV9 5D Wagon GT-Line AWD', 'Complete', 'AutoGo', '01/26/2026'],
            ['#238', 'Issac Krausz', '2026 Honda Odyssey 5D Wagon EX-L', 'Complete', 'AutoGo', '01/26/2026'],
            ['#237', 'Shmiel Mier Lowy', '2025 Lincoln Corsair 5D Wagon Grand Touring AWD', 'Outstanding', 'AutoGo', '01/26/2026'],
            ['#236', 'Avrum Lowy', '2024 Kia Telluride 4D Wagon S AWD', 'Outstanding', 'Hershy Gantz', '01/22/2026'],
            ['#235', 'Joel Werzberger', '2026 Hyundai Tucson Hybrid 5D Wagon SEL Convenience', 'Complete', 'AutoGo', '01/22/2026'],
            ['#234', 'Alter Burech Gefen', '2024 Hyundai Tucson 5D Wagon Limited', 'Lead', 'Hershy Gantz', '01/21/2026'],
            ['#233', 'Avrum Hillel Moshkowitz', '2024 Hyundai Tucson 5D Wagon SEL AWD', 'Lead', 'Hershy Gantz', '01/21/2026'],
            ['#232', 'Itmar Schwortz', '2026 Honda Odyssey 5D Wagon EX-L', 'Outstanding', 'AutoGo', '01/19/2026'],
            ['#230', 'Yakov Rosenberg', null, 'Lead', 'AutoGo', '01/19/2026'],
            ['#227', 'Yoel Paskes', '2025 Lincoln Corsair 5D Wagon Premiere AWD', 'Complete', 'AutoGo', '01/19/2026'],
            ['#226', 'Shimon Yisroel Berkowitz', '2026 Chevrolet Traverse 4D Wagon LT AWD', 'Outstanding', 'AutoGo', '01/19/2026'],
            ['#225', 'Elye Farkas', '2026 Honda HR-V 5D Wagon LX AWD', 'Outstanding', 'AutoGo', '01/19/2026'],
            ['#224', 'David Farkas', '2025 Nissan Pathfinder 4D Wagon SL 4WD', 'Complete', 'AutoGo', '01/19/2026'],
            // Page 3
            ['#223', 'Burech Lowy', '2026 GMC Yukon XL 4D Wagon Elevation 4WD', 'Outstanding', 'AutoGo', '01/15/2026'],
            ['#222', 'Shlome Berkowitz', '2025 Hyundai Elantra 4D Sedan SE', 'Outstanding', 'AutoGo', '01/15/2026'],
            ['#219', 'Ester Landau', '2026 Honda Odyssey 5D Wagon EX-L', 'Complete', 'AutoGo', '01/15/2026'],
            ['#217', 'Chaim Vanchozker', '2026 Nissan Murano 4D Wagon SV AWD', 'Complete', 'AutoGo', '01/15/2026'],
            ['#216', 'Shlome Heimlich', '2026 Hyundai Palisade 4D Wagon XRT Pro AWD', 'Complete', 'AutoGo', '01/15/2026'],
            ['#215', 'Ezriel Ekstein', '2025 Nissan Pathfinder 4D Wagon SL 4WD', 'Complete', 'AutoGo', '01/15/2026'],
            ['#214', 'Lipa Halpert', '2026 Hyundai Palisade 4D Wagon XRT Pro AWD', 'Complete', 'AutoGo', '01/15/2026'],
            ['#213', 'Aron Friedman', '2026 Hyundai Palisade 4D Wagon SEL 7 Seat AWD', 'Complete', 'AutoGo', '01/15/2026'],
            ['#212', 'yitzchok Teitelboum', '2026 Honda Odyssey 5D Wagon EX-L', 'Complete', 'AutoGo', '01/15/2026'],
            ['#209', 'Malka Alter', null, 'Lead', 'AutoGo', '01/13/2026'],
            ['#208', 'Chaim Alter', '2026 Honda Odyssey 5D Wagon EX-L', 'Complete', 'AutoGo', '01/13/2026'],
            ['#207', 'Shimen Leifer', '2026 Nissan Murano 4D Wagon SV AWD', 'Complete', 'Hershy Gantz', '01/13/2026'],
            ['#206', 'Auto Go', '2026 Honda Odyssey 5D Wagon EX-L', 'Complete', 'AutoGo', '01/12/2026'],
            ['#205', 'Hershy Gantz', null, 'Lead', 'AutoGo', '01/08/2026'],
            ['#203', 'Hershy Lowy', '2027 Kia Telluride 4D Wagon EX X-Line AWD', 'Complete', 'AutoGo', '01/08/2026'],
            ['#201', 'Shlome Yankel Liberman', '2026 Honda Odyssey 5D Wagon EX-L', 'Outstanding', 'AutoGo', '01/08/2026'],
            ['#200', 'Aron Glouber', '2026 Hyundai Palisade 4D Wagon SEL 7 Seat AWD', 'Complete', 'AutoGo', '01/07/2026'],
            ['#199', 'Aron Kraus', null, 'Lead', 'AutoGo', '01/07/2026'],
            ['#197', 'Eli Ostreicher', '2025 Hyundai Elantra 4D Sedan SEL Sport', 'Complete', 'AutoGo', '11/16/2025'],
            ['#196', 'Mordche Friedman', '2025 Toyota Tacoma 4D Double Cab SR5 4X4 Auto', 'Complete', 'AutoGo', '11/10/2025'],
            ['#195', 'Yoel Friedman', null, 'Lead', 'Hershy Gantz', '11/05/2025'],
            ['#194', 'Shimshy Gefen', null, 'Complete', 'Hershy Gantz', '11/05/2025'],
            ['#193', 'Yoel Landau', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#192', 'Burech Fogel', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#191', 'Lipa Halpert', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            // Page 4
            ['#190', 'Lipa Deutsch', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#189', 'Yosef Landau', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#188', 'Shiye Chiam Spitzer', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#187', 'Avrum Shiye Gefen', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#186', 'Kalmen Lezer Ekstein', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#185', 'Yoel Khon', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#184', 'Tzvi Spitzer', null, 'Complete', 'Hershy Gantz', '11/04/2025'],
            ['#183', 'Moshe Schwartz', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#182', 'Sharge Stein', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#181', 'Meir Berger', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#180', 'Yisroel Yakov Zilbersein', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#179', 'Chaim Lezer Davidowitz', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#178', 'Lazer Ruttner', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#177', 'Yossi Saffdie', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#176', 'Avrum Lowy', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#175', 'Kalmen Lazer Dana', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#174', 'Hersh Yakov Brizel', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#173', 'Yitzchok Deutsch', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#172', 'Shiye Mendlowitz', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#171', 'Motty Walter', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#170', 'Isaac Witriol', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#169', 'Aron Yosel Markowitz', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#168', 'Joel Blaustein', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#167', 'Mordechai Weinstock', null, 'Complete', 'Hershy Gantz', '11/02/2025'],
            ['#166', 'Chaim Mechel Schwartz', null, 'Quote', 'AutoGo', '09/11/2025'],
            // Page 5
            ['#165', 'Elye Spitzer', null, 'Lead', 'Hershy Gantz', '09/09/2025'],
            ['#164', 'Moshe Schlesinger', null, 'Lead', 'Hershy Gantz', '09/09/2025'],
            ['#163', 'Tzali Perl', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#162', 'Aron Klein', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#161', 'Berel Wertzberger', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#160', 'Sarah Danziger', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#159', 'Tovye Weider', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#158', 'Shlome Jacob', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#157', 'Yecheskel Hershkowitz', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#156', 'Yoel Perl', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#155', 'Yanet Garcia', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#154', 'Avrum Teitelbaum', null, 'Lead', 'Hershy Gantz', '09/08/2025'],
            ['#153', 'Yoel Moshe Halpert', null, 'Lead', 'Hershy Gantz', '09/04/2025'],
            ['#152', 'Kalmy Gefen', null, 'Lead', 'Hershy Gantz', '09/04/2025'],
            ['#151', 'Aron Greenwald', null, 'Lead', 'Hershy Gantz', '09/04/2025'],
            ['#150', 'Yosef Lowy', null, 'Lead', 'Hershy Gantz', '09/04/2025'],
            ['#149', 'Shmiel Krause', null, 'Lead', 'Hershy Gantz', '09/04/2025'],
            ['#148', 'Moishy Katz', null, 'Quote', 'AutoGo', '09/02/2025'],
            ['#147', 'Feivel Sofer', null, 'Lead', 'Hershy Gantz', '09/02/2025'],
            ['#146', 'Samuel Stern', null, 'Lead', 'Hershy Gantz', '09/02/2025'],
            ['#145', 'Yitzchok Weiss', null, 'Lead', 'Hershy Gantz', '09/02/2025'],
            ['#144', 'Avrumy Wertheimer', null, 'Lead', 'Hershy Gantz', '09/02/2025'],
            ['#143', 'Shiya Gefen', null, 'Lead', 'AutoGo', '08/31/2025'],
            ['#142', 'Yidel Hershkowitz', null, 'Outstanding', 'AutoGo', '08/31/2025', 'high'],
            ['#141', 'Yakov Dov Cohen', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            // Page 6
            ['#140', 'Yosef Gantz', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#139', 'Yisroel Mendel Schwatrz', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#138', 'Mair David Tabak', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#137', 'Aron Menachem Ungar', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#136', 'Moshe Scher', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#135', 'Leib parnes Mair Berger', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#134', 'Moshe Friedman', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#133', 'Nuchem Shmiel Gluck', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#132', 'Yoel Tyrnauer', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#131', 'Pinchus Eidlisz Lezer Yongreiz', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#130', 'Shaya Weiss', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#129', 'Mordche Friedman', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#128', 'Joel Konigsberg', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#127', 'Shiye Fischer', null, 'Lead', 'Hershy Gantz', '08/29/2025'],
            ['#126', 'Yoel Katz', null, 'Quote', 'Hershy Gantz', '08/26/2025'],
            ['#125', 'Berl Witriol', null, 'Lead', 'Hershy Gantz', '08/26/2025'],
            ['#124', 'Chaim Guttlieb', null, 'Lead', 'Hershy Gantz', '08/26/2025'],
            ['#122', 'Yoel Fuchs', null, 'Lead', 'Hershy Gantz', '08/26/2025'],
            ['#121', 'Hershel Katz', null, 'Lead', 'Hershy Gantz', '08/25/2025'],
            ['#120', 'Shulem Joseph Gross', null, 'Lead', 'Hershy Gantz', '08/25/2025'],
            ['#119', 'Alter Gefen', null, 'Lead', 'Hershy Gantz', '08/25/2025'],
            ['#118', 'Yakov Lev', null, 'Lead', 'Hershy Gantz', '08/25/2025'],
            ['#117', 'Lipa yoel Fisher', null, 'Lead', 'Hershy Gantz', '08/21/2025'],
            ['#116', 'Shmiel Brach', null, 'Lead', 'Hershy Gantz', '08/21/2025'],
            ['#115', 'Dovid Wertheimer', null, 'Lead', 'Hershy Gantz', '08/21/2025'],
            // Page 7
            ['#114', 'Yide Hersh Gefen', null, 'Lead', 'Hershy Gantz', '08/21/2025'],
            ['#113', 'Yoel Goldenberg', null, 'Lead', 'Hershy Gantz', '08/20/2025'],
            ['#112', 'Yisroel Davidowitz', null, 'Lead', 'Hershy Gantz', '08/20/2025'],
            ['#111', 'Aron Isreal Masri', null, 'Lead', 'Hershy Gantz', '08/20/2025'],
            ['#110', 'Eliezer Greenwald', null, 'Lead', 'Hershy Gantz', '08/20/2025'],
            ['#109', 'Shiye Friedman', null, 'Lead', 'Hershy Gantz', '08/20/2025'],
            ['#108', 'Yidel Lichtenstein', null, 'Lead', 'Hershy Gantz', '08/20/2025'],
            ['#107', 'Yoel Shome Jacob', null, 'Lead', 'Hershy Gantz', '08/20/2025'],
            ['#106', 'Alter Halpert', null, 'Lead', 'Hershy Gantz', '08/18/2025'],
            ['#105', 'Shmiel Goldstein', null, 'Quote', 'AutoGo', '08/14/2025'],
            ['#103', 'Yide Feder', null, 'Complete', 'AutoGo', '08/13/2025'],
            ['#102', 'Hershy Friedrich', null, 'Quote', 'AutoGo', '08/06/2025'],
            ['#100', 'Mordche Davidowitz', null, 'Complete', 'AutoGo', '08/03/2025'],
        ];

        $stageMap = [
            'Lead' => 'lead',
            'Quote' => 'quote',
            'Application' => 'application',
            'Submission' => 'submission',
            'Pending' => 'pending',
            'Finalize' => 'finalize',
            'Outstanding' => 'outstanding',
            'Complete' => 'complete',
        ];

        $imported = 0;
        foreach ($deals as $row) {
            [$dealNum, $name, $vehicle, $stage, $sp, $created] = array_pad($row, 6, null);
            $priority = $row[6] ?? 'low';

            $dealNumber = (int) ltrim($dealNum, '#');

            if (Deal::where('deal_number', $dealNumber)->exists()) continue;

            // Find/create customer
            $parts = explode(' ', trim($name));
            $lastName = count($parts) >= 2 ? array_pop($parts) : '';
            $firstName = count($parts) >= 1 ? implode(' ', $parts) : $name;

            $customer = Customer::where('first_name', $firstName)->where('last_name', $lastName)->first()
                ?? Customer::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'is_active' => true,
                    'can_receive_sms' => true,
                ]);

            // Parse vehicle
            $vyear = null; $vmake = null; $vmodel = null; $vtrim = null;
            if ($vehicle) {
                if (preg_match('/^(\d{4})\s+(\w+)\s+([\w-]+)\s*(.*)$/', $vehicle, $m)) {
                    $vyear = (int) $m[1];
                    $vmake = $m[2];
                    $vmodel = $m[3];
                    $vtrim = trim($m[4]) ?: null;
                }
            }

            $salesperson = $sp === 'Hershy Gantz' ? $hershyGantz : $autoGoOffice;

            Deal::create([
                'deal_number' => $dealNumber,
                'customer_id' => $customer->id,
                'salesperson_id' => $salesperson->id,
                'vehicle_year' => $vyear,
                'vehicle_make' => $vmake,
                'vehicle_model' => $vmodel,
                'vehicle_trim' => $vtrim,
                'payment_type' => 'lease',
                'stage' => $stageMap[$stage] ?? 'lead',
                'priority' => $priority,
                'deal_start_date' => \Carbon\Carbon::createFromFormat('m/d/Y', $created),
            ]);

            $imported++;
        }

        $this->command->info("Imported {$imported} deals from xDeskPro.");
    }
}
