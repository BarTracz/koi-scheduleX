import csv
import datetime
import random
from collections import defaultdict
import argparse # Import argparse

# --- Argument Parser ---
parser = argparse.ArgumentParser(description='Generate a schedule based on streamer availability and personalities.')
parser.add_argument('--personalities', required=True, help='Path to the personalities file (e.g., osobowosci.txt)')
parser.add_argument('--calendar', required=True, help='Path to the calendar CSV file (e.g., koi-calendar-export.csv)')
parser.add_argument('--output', required=True, help='Path for the output schedule CSV file (e.g., harmonogram.csv)')
parser.add_argument('--year', required=True, type=int, help='The year for the schedule.')
parser.add_argument('--month', required=True, type=int, help='The month for the schedule.')
parser.add_argument('--stream_duration', type=int, default=3, help='Default duration of a stream in hours.')
parser.add_argument('--max_concurrent_streams', type=int, default=2, help='Maximum number of concurrent streams in one slot.')
parser.add_argument('--min_streams_per_day', type=int, default=3, help='Minimum total streams per day.')
parser.add_argument('--max_streams_per_streamer', type=int, default=16, help='Maximum streams per streamer per month.')
parser.add_argument('--min_streams_per_bucket', type=int, default=5, help='Minimum streams per time bucket for each streamer.')
parser.add_argument('--min_streams_per_week', type=int, default=0, help='Minimum streams per streamer per week.')
args = parser.parse_args()

# --- Configuration ---
PERSONALITIES_FILE = args.personalities
CALENDAR_FILE = args.calendar
OUTPUT_FILE = args.output
YEAR = args.year
MONTH = args.month

# --- Scheduling Rules ---
STREAM_DURATION_HOURS = args.stream_duration
MAX_CONCURRENT_STREAMS = args.max_concurrent_streams
MIN_STREAMS_PER_DAY = args.min_streams_per_day
TARGET_AND_MAX_STREAMS = args.max_streams_per_streamer
MIN_STREAMS_PER_BUCKET = args.min_streams_per_bucket
MIN_STREAMS_PER_WEEK = args.min_streams_per_week
 
if MONTH == 12:
    FIRST_DAY_NEXT_MONTH = datetime.date(YEAR + 1, 1, 1)
else:
    FIRST_DAY_NEXT_MONTH = datetime.date(YEAR, MONTH + 1, 1)
LAST_DAY_OF_MONTH = FIRST_DAY_NEXT_MONTH - datetime.timedelta(days=1)
DAYS_IN_MONTH = LAST_DAY_OF_MONTH.day

# Time bucket definitions
TIME_BUCKETS = {
    'morning': [12, 10], # Prefer 12:00 over 10:00
    'afternoon': [15],
    'evening': [20, 18] # Prefer 20:00 over 18:00
}

# Reverse map for quick checking
SLOT_TO_BUCKET_MAP = {
    12: 'morning', 10: 'morning',
    15: 'afternoon',
    20: 'evening', 18: 'evening'
}

# Slots that MUST be filled
MANDATORY_SLOTS = [12, 15, 20]
# All slots the algorithm can use for planning
SCHEDULABLE_SLOTS = [12, 15, 20, 10, 18]


# --- Data Structures ---
personalities = {}
availability = defaultdict(dict)
schedule = defaultdict(list)
quota_stats = defaultdict(lambda: defaultdict(int)) 


# --- Helper Functions ---

def load_personalities():
    try:
        with open(PERSONALITIES_FILE, 'r', encoding='utf-8') as f:
            reader = csv.reader(f)
            for row in reader:
                if row: # Check if the row is not empty
                    try:
                        name, p_type = row[0], row[1]
                        personalities[name.strip()] = int(p_type.strip())
                    except (ValueError, IndexError):
                        pass # Silently skip invalid lines
    except FileNotFoundError:
        return False
    return True

def load_availability():
    try:
        with open(CALENDAR_FILE, 'r', encoding='utf-8') as f:
            reader = csv.reader(f)
            next(reader)  # Skip header
            for row in reader:
                try:
                    talent, date_str, start_str, end_str = row[0], row[1], row[2], row[3]
                    date_obj = datetime.datetime.strptime(date_str, '%Y-%m-%d').date()
                    if date_obj.year != YEAR or date_obj.month != MONTH:
                        continue
                    start_h = int(start_str.split(':')[0])
                    end_h = int(end_str.split(':')[0])
                    availability[talent][date_obj] = (start_h, end_h)
                except (ValueError, IndexError):
                    pass # Silently skip invalid lines
    except FileNotFoundError:
        return False
    return True

def is_streamer_available(streamer, day, slot_start):
    slot_end = slot_start + STREAM_DURATION_HOURS
    if day not in availability[streamer]:
        return False
    avail_start, avail_end = availability[streamer][day]
    return avail_start <= slot_start and avail_end >= slot_end

# --- UPDATED can_add_stream function ---
def can_add_stream(streamer, day, slot_start):
    # 1. Availability
    if not is_streamer_available(streamer, day, slot_start):
        return False
        
    # 2. Check monthly maximum (16)
    if quota_stats[streamer]['total'] >= TARGET_AND_MAX_STREAMS:
        return False # Streamer has already hit the 16 stream limit

    # 3. Limit 1 stream per day
    for (d, t), stream_list in schedule.items():
        if d == day and streamer in stream_list:
            return False
            
    # 4. Limit 2 streams per slot
    if len(schedule.get((day, slot_start), [])) >= MAX_CONCURRENT_STREAMS:
        return False
        
    # 5. Personality conflict
    my_type = personalities.get(streamer, -1)
    my_start = slot_start
    my_end = slot_start + STREAM_DURATION_HOURS
    for (d, other_start), other_streamers in schedule.items():
        if d != day:
            continue
        other_end = other_start + STREAM_DURATION_HOURS
        if my_start < other_end and other_start < my_end:
            for other_streamer in other_streamers:
                if personalities.get(other_streamer, -2) == my_type:
                    return False
    return True

# --- add_stream function ---
def add_stream(streamer, day, slot_start):
    """Adds a stream and updates BUCKET stats."""
    schedule[(day, slot_start)].append(streamer)
    
    quota_stats[streamer]['total'] += 1
    
    bucket = SLOT_TO_BUCKET_MAP.get(slot_start)
    if bucket:
        quota_stats[streamer][bucket] += 1
    
    week_number = day.isocalendar()[1]
    quota_stats[streamer][f'week_{week_number}'] += 1


# --- Main Algorithm ---

def create_schedule():
    
    if not load_personalities() or not load_availability():
        return

    streamers = list(personalities.keys())
    random.shuffle(streamers) 
    all_days = [datetime.date(YEAR, MONTH, d) for d in range(1, DAYS_IN_MONTH + 1)]
    
    # --- Phase 1: Fill BUCKET quotas (e.g., 5x 'morning') ---
    print("\n--- Phase 1: Filling bucket quotas ---")
    
    for s in streamers:
        
        for bucket, slots_in_bucket in TIME_BUCKETS.items():
            
            missing_count = MIN_STREAMS_PER_BUCKET - quota_stats[s][bucket]
            if missing_count <= 0:
                continue
            
            available_options = []
            for slot in slots_in_bucket: 
                for day in all_days:
                    if is_streamer_available(s, day, slot):
                        available_options.append((day, slot))
                        
            random.shuffle(available_options)
            
            added_count = 0
            for (day, slot) in available_options:
                if added_count >= missing_count:
                    break 
                
                # can_add_stream will check availability, conflicts, AND max 16 limit
                if can_add_stream(s, day, slot):
                    add_stream(s, day, slot)
                    added_count += 1

    # --- Phase 1.5: Fill weekly quotas ---
    if MIN_STREAMS_PER_WEEK > 0:
        print("\n--- Phase 1.5: Filling weekly quotas ---")
        weeks_in_month = sorted(list(set(d.isocalendar()[1] for d in all_days)))

        for s in streamers:
            for week_num in weeks_in_month:
                days_in_week = [d for d in all_days if d.isocalendar()[1] == week_num]
                
                current_weekly_streams = quota_stats[s].get(f'week_{week_num}', 0)
                missing_for_week = MIN_STREAMS_PER_WEEK - current_weekly_streams

                if missing_for_week <= 0:
                    continue

                available_options = []
                for slot in SCHEDULABLE_SLOTS:
                    for day in days_in_week:
                        if is_streamer_available(s, day, slot):
                            available_options.append((day, slot))
                
                random.shuffle(available_options)

                added_count = 0
                for (day, slot) in available_options:
                    if added_count >= missing_for_week: break
                    if can_add_stream(s, day, slot):
                        add_stream(s, day, slot)
                        added_count += 1

    # --- Phase 2: Ensure min 3/day and fill 12, 15, 20 ---
    print("\n--- Phase 2: Ensuring daily minimums and filling mandatory slots ---")
    
    filler_streamers = list(personalities.keys())
    
    for day in all_days:
        # Check if MANDATORY slots (12, 15, 20) are filled
        for slot in MANDATORY_SLOTS:
            if not schedule.get((day, slot), []): # If empty
                random.shuffle(filler_streamers)
                for s in filler_streamers:
                    # can_add_stream will check if this streamer has hit 16
                    if can_add_stream(s, day, slot):
                        add_stream(s, day, slot)
                        break

        # Check if there are 3 streams per day
        streams_this_day = sum(len(stream_list) for (d, t), stream_list in schedule.items() if d == day)
        
        if streams_this_day < MIN_STREAMS_PER_DAY:
            
            missing_count = MIN_STREAMS_PER_DAY - streams_this_day
            added_count = 0
            
            for slot in SCHEDULABLE_SLOTS: 
                if added_count >= missing_count: break
                random.shuffle(filler_streamers)
                for s in filler_streamers:
                    if added_count >= missing_count: break
                    if can_add_stream(s, day, slot):
                        add_stream(s, day, slot)
                        added_count += 1

    # --- Fill up to the 16 stream target/max ---
    print(f"\n--- Phase 3: Filling up to the {TARGET_AND_MAX_STREAMS} stream target/max ---")
    for s in streamers: # Using the shuffled list
        # Check how many streams are needed to hit the target of 16
        missing_for_target_16 = TARGET_AND_MAX_STREAMS - quota_stats[s]['total']
        
        if missing_for_target_16 > 0:
            added_count = 0
            random_days = list(all_days)
            random.shuffle(random_days)
            
            for slot in SCHEDULABLE_SLOTS: # Try 12, 15, 20, 10, 18
                if added_count >= missing_for_target_16: break
                for day in random_days:
                    if added_count >= missing_for_target_16: break
                    
                    # can_add_stream will check all rules (availability, max limit, daily limit, conflict)
                    if can_add_stream(s, day, slot):
                        add_stream(s, day, slot)
                        added_count += 1

    print("\n--- Generating CSV file ---")
    sorted_slots = sorted(schedule.items())
    
    with open(OUTPUT_FILE, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerow(['talent', 'date', 'start_time'])
        for (day, slot), streamers_in_slot in sorted_slots:
            slot_str = f"{slot:02d}:00"
            data_str = day.strftime('%Y-%m-%d')
            for s in streamers_in_slot:
                writer.writerow([s, data_str, slot_str])
    
    print(f"Finished! Schedule saved to {OUTPUT_FILE}")
    # --- Final Stats ---
    print("\n--- Final Stats (Buckets) ---")
    
    print(f"{'Streamer':<15} | {'Total':>5} | {'Morning':>7} | {'Afternoon':>9} | {'Evening':>7}")
    
    print("-" * 55)
    
    for s in sorted(personalities.keys()):
        stats = quota_stats[s]
        print(f"{s:<15} | {stats['total']:>5} | {stats.get('morning', 0):>7} | {stats.get('afternoon', 0):>9} | {stats.get('evening', 0):>7}")

# --- Execution ---
if __name__ == "__main__":
    create_schedule()